import axios from "axios";
import React, { useState, useEffect, useRef, useMemo } from "react";
import toast, { Toaster } from "react-hot-toast";
import Swal from "sweetalert2";
import SuccessSound from "../sounds/beep-07a.mp3";
import WarningSound from "../sounds/beep-02.mp3";
import playSound from "../utils/playSound";
import PackingModal from "./modals/PackingModal";

const PriceInput = ({ item, onPriceSave }) => {
    const [value, setValue] = useState(
        item.unit_price ?? item.product.discounted_price ?? ""
    );

    useEffect(() => {
        setValue(item.unit_price ?? item.product.discounted_price ?? "");
    }, [item.id, item.unit_price]);

    const handleBlur = () => {
        const price = parseFloat(value);
        if (!isNaN(price) && price >= 0) {
            onPriceSave(item.id, price);
        }
    };

    return (
        <input
            type="number"
            className="border text-right"
            style={{ width: "80px" }}
            value={value}
            step="any"
            min="0"
            onChange={(e) => setValue(e.target.value)}
            onBlur={handleBlur}
        />
    );
};

export default function Cart({
    carts,
    setCartUpdated,
    isRegistered,
    onPriceSave,
    onQuantityChange,
    onTotalsChange,
    quantityValidation,
}) {
    const [editingQuantity, setEditingQuantity] = useState({});
    const [packingModalItem, setPackingModalItem] = useState(null);
    const pendingUpdates = useRef({});
    const debounceTimers = useRef({});
    const toastIdRef = useRef({});

    const registrationStatus = isRegistered || "Registered";

    function roundTo(val, decimals = 4) {
        return Math.round(val * Math.pow(10, decimals)) / Math.pow(10, decimals);
    }

    // Single source of truth: Pos `carts`.
    // If a refetch lands while a quantity save is still pending,
    // keep the user's newer quantity on top of the server data.
    const computed = useMemo(() => {
        let subTotalExTax = 0;
        let furtherTaxCalc = 0;
        let saleTaxCalc = 0;

        const items = carts.map((raw) => {
            const isPacked = Boolean(raw.packing_id || (raw.packing_levels && raw.packing_levels.length > 0));
            const factor = Number(raw.base_qty_factor) || 1;

            const pendingQty = pendingUpdates.current[raw.id];
            const displayQty = pendingQty !== undefined
                ? Number(pendingQty)
                : (isPacked && raw.entered_qty !== undefined && raw.entered_qty !== null ? Number(raw.entered_qty) : Number(raw.quantity));

            const baseQty = isPacked ? roundTo(displayQty * factor, 4) : displayQty;
            const price = Number(raw.unit_price ?? raw.product?.discounted_price ?? 0);
            const exTaxAmount = price * displayQty;
            const rateText = String(raw.product?.rate_of_tax ?? "0").trim();

            const saleTax = rateText.endsWith("%")
                ? exTaxAmount * (parseFloat(rateText) / 100)
                : (parseFloat(rateText) || 0) * baseQty;

            const furtherTax = raw.product?.sale_type?.trim().toLowerCase() !== "3rd schedule goods" &&
                registrationStatus.trim().toLowerCase() === "unregistered" ? exTaxAmount * 0.04 : 0;

            const item = {
                ...raw,
                display_qty: displayQty,
                base_qty_calculated: baseQty,
                factor,
                is_packed: isPacked,
            };

            if (!item.parent_cart_id && !item.is_accessory) {
                subTotalExTax += exTaxAmount;
                saleTaxCalc += saleTax;
                furtherTaxCalc += furtherTax;
            }

            return {
                ...item,
                base_total: exTaxAmount.toFixed(2),
                sale_tax: saleTax.toFixed(2),
                further_tax: furtherTax.toFixed(2),
                total_with_tax: (exTaxAmount + furtherTax + saleTax).toFixed(2),
            };
        });

        return { items, subTotalExTax, saleTaxCalc, furtherTaxCalc };
    }, [carts, registrationStatus]);

    const cartItems = computed.items;

    // Tell Pos the totals whenever they change
    useEffect(() => {
        onTotalsChange?.(
            computed.subTotalExTax,
            computed.saleTaxCalc,
            computed.furtherTaxCalc,
            computed.subTotalExTax + computed.saleTaxCalc + computed.furtherTaxCalc
        );
    }, [computed]);

    const stockOf = (item) => {
        if (!item || item._optimistic) return Infinity;
        return Number(item.available_stock ?? item.product?.quantity ?? Infinity);
    };

    const handleSetUnit = async (cartId, qtyUnitId) => {
        try {
            const res = await axios.put("/admin/cart/set-unit", {
                id: cartId,
                qty_unit_id: qtyUnitId,
            });

            playSound(SuccessSound);
            toast.success(res?.data?.message || "Packaging unit updated");
            setCartUpdated((prev) => !prev);

            return true;
        } catch (err) {
            playSound(WarningSound);
            toast.error(err.response?.data?.message || "Failed to change unit");

            return false;
        }
    };

    function updateQuantity(id, newQuantity) {
        // 1. Instant UI + totals (updates Pos `carts`)
        onQuantityChange?.(id, newQuantity);

        // 2. Debounced save
        if (debounceTimers.current[id]) {
            clearTimeout(debounceTimers.current[id]);
        }
        pendingUpdates.current[id] = newQuantity;

        debounceTimers.current[id] = setTimeout(() => {
            const finalQty = pendingUpdates.current[id];

            toastIdRef.current[id] = toast.loading("Updating quantity...", {
                id: toastIdRef.current[id],
            });

            axios
                .put("/admin/cart/update-quantity", { id, quantity: finalQty })
                .then((res) => {
                    playSound(SuccessSound);
                    toast.success(res?.data?.message || "Quantity updated", {
                        id: toastIdRef.current[id],
                        duration: 1500,
                    });
                    delete toastIdRef.current[id];
                })
                .catch((err) => {
                    playSound(WarningSound);
                    toast.error(err.response?.data?.message || "Something went wrong", {
                        id: toastIdRef.current[id],
                        duration: 2500,
                    });
                    delete toastIdRef.current[id];
                    delete pendingUpdates.current[id];
                    setCartUpdated((prev) => !prev); // refetch to correct the UI
                })
                .finally(() => {
                    // only clear if the user didn't change it again during the request
                    if (pendingUpdates.current[id] === finalQty) {
                        delete pendingUpdates.current[id];
                    }
                    setEditingQuantity((prev) => ({ ...prev, [id]: false }));
                });
        }, 300);
    }

    function handleQuantityBlur(id, value) {
        const item = cartItems.find((cart) => cart.id === id);
        if (!item || item._optimistic) {
            setEditingQuantity((prev) => ({ ...prev, [id]: false }));
            return;
        }
        const isAccessory = !!item.parent_cart_id || !!item.is_accessory;
        const stockEnforced = quantityValidation == "disabled";
        const newQuantity = parseFloat(value);

        if (isNaN(newQuantity) || newQuantity < 0) {
            setEditingQuantity((prev) => ({ ...prev, [id]: false }));
            return;
        }

        const factor = Number(item.base_qty_factor) || 1;
        const newBaseQty = newQuantity * factor;

        if (!isAccessory && stockEnforced && newBaseQty > stockOf(item)) {
            playSound(WarningSound);
            toast.error(`Only ${stockOf(item)} base items available in stock`);
            setEditingQuantity((prev) => ({ ...prev, [id]: false }));
            return;
        }

        updateQuantity(id, newQuantity);
    }

    function increment(id) {
        const item = cartItems.find((cart) => cart.id === id);
        if (!item || item._optimistic) return;

        const isAccessory = !!item.parent_cart_id || !!item.is_accessory;
        const stockEnforced = quantityValidation == "disabled";
        const currentQty = Number(item.display_qty ?? item.entered_qty ?? item.quantity);
        const factor = Number(item.base_qty_factor) || 1;
        const nextBaseQty = (currentQty + 1) * factor;

        if (isAccessory || !stockEnforced || nextBaseQty <= stockOf(item)) {
            updateQuantity(id, currentQty + 1);
        }
    }

    function decrement(id) {
        const item = cartItems.find((cart) => cart.id === id);
        if (!item || item._optimistic) return;
        const currentQty = Number(item.display_qty ?? item.entered_qty ?? item.quantity);
        if (currentQty <= 1) return;
        updateQuantity(id, parseFloat((currentQty - 1).toFixed(2)));
    }

    function destroy(id) {
        const item = cartItems.find((cart) => cart.id === id);
        if (!item || item._optimistic) return;
        Swal.fire({
            title: "Are you sure you want to delete this item?",
            showDenyButton: true,
            confirmButtonText: "Yes",
            denyButtonText: "No",
        }).then((result) => {
            if (result.isConfirmed) {
                axios
                    .put("/admin/cart/delete", { id })
                    .then((res) => {
                        setCartUpdated((prev) => !prev);
                        playSound(SuccessSound);
                        toast.success(res?.data?.message);
                    })
                    .catch((err) => {
                        toast.error(err.response?.data?.message || "Failed to delete");
                    });
            }
        });
    }

    const shouldApplyFurtherTax = (item) => {
        if (!item || !item.product || !registrationStatus) return false;
        return (
            item.product.sale_type?.trim().toLowerCase() !== "3rd schedule goods" &&
            registrationStatus.toLowerCase() === "unregistered"
        );
    };

    // Group: build a map of parent_cart_id -> [accessory items]
    const accessoryMap = {};
    cartItems.forEach((item) => {
        if (item.parent_cart_id || item.is_accessory) {
            const pid = item.parent_cart_id;
            if (!accessoryMap[pid]) accessoryMap[pid] = [];
            accessoryMap[pid].push(item);
        }
    });

    // Only top-level (parent) items for main table rows
    const parentItems = cartItems.filter((i) => !i.parent_cart_id && !i.is_accessory);

    return (
        <>
            <div className="user-cart">
                <div className="card">
                    <div className="card-body">
                        <div className="responsive-table">
                            <table className="table table-striped">
                                <thead>
                                    <tr className="text-center">
                                        <th>Name</th>
                                        <th>Quantity</th>
                                        <th></th>
                                        <th>Price</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    {parentItems.length > 0 ? (
                                        parentItems.map((item) => {
                                            if (!item) return null;
                                            const applyFurtherTax = shouldApplyFurtherTax(item);
                                            const itemAccessories = accessoryMap[item.id] || [];
                                            const hasPacking = Boolean(
                                                item.packing_levels && item.packing_levels.length > 0
                                            );
                                            const factor = Number(item.base_qty_factor) || 1;
                                            const displayQty = item.display_qty ?? 1;
                                            const baseQty = item.base_qty_calculated ?? item.quantity;
                                            const unitName = item.qty_unit_name || item.base_unit_name || "Unit";
                                            const baseUnitName = item.base_unit_name || "Piece";

                                            return (
                                                <React.Fragment key={item.id}>
                                                    {/* ── PARENT ROW ── */}
                                                    <tr>
                                                        <td>
                                                            {item._optimistic ? (
                                                                <span className="text-muted"><i className="fas fa-spinner fa-spin"></i> Adding...</span>
                                                            ) : (

                                                                <div className="d-flex flex-column align-items-start">

                                                                    {/* Product name and packaging unit in one line */}
                                                                    <div className="d-flex align-items-center flex-nowrap"
                                                                        style={{ gap: "8px", minWidth: 0, maxWidth: "100%" }}
                                                                    >
                                                                        <strong
                                                                            className="text-dark"
                                                                            style={{
                                                                                fontSize: "13px",
                                                                                whiteSpace: "nowrap",
                                                                                overflow: "hidden",
                                                                                textOverflow: "ellipsis",
                                                                            }}
                                                                            title={item.product.name}
                                                                        >
                                                                            {item.product.name}
                                                                        </strong>

                                                                        {hasPacking && (
                                                                            <button type="button"
                                                                                onClick={() => setPackingModalItem(item)}
                                                                                title="Change packaging unit"
                                                                                className="packing-unit-btn"
                                                                            >
                                                                                {unitName} ({factor})
                                                                            </button>
                                                                        )}
                                                                    </div>

                                                                    {/* Variation */}
                                                                    {item.variation_label && (
                                                                        <div className="small text-muted mt-1">
                                                                            {item.variation_label}
                                                                        </div>
                                                                    )}

                                                                    {/* Accessories */}
                                                                    {itemAccessories.length > 0 && (
                                                                        <span className="badge badge-info mt-1"
                                                                            style={{ fontSize: "0.7em" }}
                                                                        >
                                                                            <i className="fas fa-puzzle-piece"></i>{" "}
                                                                            {itemAccessories.length}
                                                                        </span>
                                                                    )}
                                                                </div>

                                                            )}
                                                        </td>

                                                        <td>
                                                            <div className="d-flex align-items-center">
                                                                <button
                                                                    className="btn btn-warning btn-sm"
                                                                    onClick={() => decrement(item.id)}
                                                                    disabled={item._optimistic || displayQty <= 1}
                                                                >
                                                                    <i className="fas fa-minus"></i>
                                                                </button>

                                                                {editingQuantity[item.id] ? (
                                                                    <input
                                                                        type="number"
                                                                        className="form-control form-control-sm qty ml-1 mr-1"
                                                                        defaultValue={displayQty}
                                                                        min="0.01"
                                                                        step="any"
                                                                        autoFocus
                                                                        onBlur={(e) =>
                                                                            handleQuantityBlur(item.id, e.target.value)
                                                                        }
                                                                        onKeyPress={(e) => {
                                                                            if (e.key === "Enter") {
                                                                                handleQuantityBlur(item.id, e.target.value);
                                                                            }
                                                                        }}
                                                                        style={{ width: "65px" }}
                                                                    />
                                                                ) : (
                                                                    <div
                                                                        className="form-control form-control-sm qty ml-1 mr-1 text-center font-weight-bold"
                                                                        style={{
                                                                            width: "65px",
                                                                            cursor: "pointer",
                                                                            backgroundColor: "#f8f9fa",
                                                                        }}
                                                                        onClick={() => {
                                                                            if (item._optimistic) return;
                                                                            setEditingQuantity((prev) => ({ ...prev, [item.id]: true }));
                                                                        }}
                                                                    >
                                                                        {displayQty}
                                                                    </div>
                                                                )}

                                                                <button
                                                                    className="btn btn-success btn-sm"
                                                                    onClick={() => increment(item.id)}
                                                                    disabled={item._optimistic ||
                                                                        (quantityValidation == "disabled" && (displayQty + 1) * factor > stockOf(item))
                                                                    }
                                                                >
                                                                    <i className="fas fa-plus"></i>
                                                                </button>
                                                            </div>

                                                            {factor > 1 && (
                                                                <div
                                                                    className="text-muted text-center mt-1"
                                                                    style={{ fontSize: "11px", whiteSpace: "nowrap" }}
                                                                >
                                                                    = {baseQty} {baseUnitName}
                                                                </div>
                                                            )}
                                                        </td>

                                                        <td>
                                                            <button
                                                                className="btn btn-danger btn-sm mr-3"
                                                                onClick={() => destroy(item.id)}
                                                            >
                                                                <i className="fas fa-trash"></i>
                                                            </button>
                                                        </td>

                                                        <td className="text-right">
                                                            <PriceInput item={item} onPriceSave={onPriceSave} />
                                                        </td>

                                                        <td className="text-right">
                                                            {applyFurtherTax ? (
                                                                <>
                                                                    <div className="text-success font-weight-bold">
                                                                        {item.total_with_tax}
                                                                    </div>
                                                                    <div className="small text-muted">
                                                                        <del>{item.base_total}</del>
                                                                    </div>
                                                                    <div className="small text-danger">
                                                                        +{item.further_tax} further tax
                                                                    </div>
                                                                    <div className="small text-warning">
                                                                        +{item.sale_tax} sale tax
                                                                    </div>
                                                                </>
                                                            ) : (
                                                                <>
                                                                    <div className="font-weight-bold">
                                                                        {item.base_total}
                                                                    </div>
                                                                    <div className="small text-warning">
                                                                        +{item.sale_tax} sale tax
                                                                    </div>
                                                                </>
                                                            )}
                                                        </td>
                                                    </tr>
                                                </React.Fragment>
                                            );
                                        })
                                    ) : (
                                        <tr>
                                            <td colSpan="5" className="text-center">
                                                Cart is empty
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {cartItems.length > 0 &&
                            registrationStatus.toLowerCase() === "unregistered" && (
                                <div className="alert alert-info mt-3 mb-0">
                                    <small>
                                        4% further tax is applied on 3rd Schedule Goods
                                        for unregistered customers.
                                    </small>
                                </div>
                            )}
                    </div>
                </div>
            </div>

            <PackingModal
                isOpen={!!packingModalItem}
                item={packingModalItem}
                onClose={() => setPackingModalItem(null)}
                onSelectUnit={handleSetUnit}
                skipIfSameUnit
            />

            <Toaster position="top-right" reverseOrder={false} />
        </>
    );
}