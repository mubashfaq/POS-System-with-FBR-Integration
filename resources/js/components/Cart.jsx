import axios from "axios";
import React, { useState, useEffect, useRef } from "react";
import toast, { Toaster } from "react-hot-toast";
import Swal from "sweetalert2";
import SuccessSound from "../sounds/beep-07a.mp3";
import WarningSound from "../sounds/beep-02.mp3";
import playSound from "../utils/playSound";

const PriceInput = ({ item, onPriceSave }) => {
    const [value, setValue] = useState(
        item.unit_price ?? item.product.discounted_price ?? ""
    );

    // Only reset if the item identity changes (new product row)
    useEffect(() => {
        setValue(item.unit_price ?? item.product.discounted_price ?? "");
    }, [item.id]);

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
    cartUpdated,
    isRegistered,
    // customerTaxType,
    onPriceChange,
    onPriceSave,
    onTotalsChange,
    quantityValidation
}) {
    const [editingQuantity, setEditingQuantity] = useState({});
    const [cartItems, setCartItems] = useState([]);
    const [subTotal, setSubTotal] = useState(0);
    const [furtherTaxTotal, setFurtherTaxTotal] = useState(0);
    // const [customerTaxTotal, setCustomerTaxTotal] = useState(0);
    const [grandTotal, setGrandTotal] = useState(0);
    const [localCarts, setLocalCarts] = useState(carts);
    const pendingUpdates = useRef({});
    const debounceTimers = useRef({});
    const toastIdRef = useRef({}); // per-item toast id


    useEffect(() => {
        setLocalCarts((prev) => {
            // If no pending updates, just take server data
            if (Object.keys(pendingUpdates.current).length === 0) {
                return carts;
            }
            // Otherwise, keep local quantities for items with pending updates
            return carts.map((serverItem) => {
                const pendingQty = pendingUpdates.current[serverItem.id];
                return pendingQty !== undefined ? { ...serverItem, quantity: pendingQty } : serverItem;
            });
        });
    }, [carts]);

    const registrationStatus = isRegistered || "Registered";

    useEffect(() => {
        let subTotalExTax = 0;
        let furtherTaxCalc = 0;
        let saleTaxCalc = 0;
        // let customerTaxCalc = 0;

        const updatedCarts = localCarts.map((item) => {
            const price = Number(item.unit_price ?? item.product.discounted_price);
            const baseTotal = price * item.quantity;
            const rateText = String(item.product.rate_of_tax).trim();

            let saleTax = 0;
            let exTaxAmount = baseTotal;

            if (rateText.endsWith("%")) {
                const rate = parseFloat(rateText.replace("%", ""));
                saleTax = exTaxAmount * (rate / 100);
            } else {
                const ratePerUnit = parseFloat(rateText) || 0;
                saleTax = ratePerUnit * item.quantity;
            }

            let furtherTax = 0;
            if (
                item.product.sale_type?.trim().toLowerCase() !== "3rd schedule goods" &&
                registrationStatus?.trim().toLowerCase() === "unregistered"
            ) {
                furtherTax = exTaxAmount * (4 / 100);
            }

            // let customerTax = 0;

            // if (customerTaxType === "retailer") {
            //     customerTax = exTaxAmount * 0.005; // 0.5%
            // } else if (customerTaxType === "distributor") {
            //     customerTax = exTaxAmount * 0.01; // 1%
            // }

            // Only accumulate from parent items
            if (!item.parent_cart_id && !item.is_accessory) {
                subTotalExTax += exTaxAmount;
                saleTaxCalc += saleTax;
                furtherTaxCalc += furtherTax;
                // customerTaxCalc += customerTax;
            }

            return {
                ...item,
                base_total: exTaxAmount.toFixed(2),
                sale_tax: saleTax.toFixed(2),
                further_tax: furtherTax.toFixed(2),
                // customer_tax: customerTax.toFixed(2),
                total_with_tax: (exTaxAmount + furtherTax + saleTax).toFixed(2),
            };
        });

        const grandTotal = subTotalExTax + saleTaxCalc + furtherTaxCalc;

        setCartItems(updatedCarts);
        setSubTotal(subTotalExTax.toFixed(2));
        setFurtherTaxTotal(furtherTaxCalc.toFixed(2));
        setGrandTotal(grandTotal.toFixed(2));
        // setCustomerTaxTotal(customerTaxCalc.toFixed(2));

        if (onTotalsChange) {
            onTotalsChange(subTotalExTax, saleTaxCalc, furtherTaxCalc, grandTotal);
        }
    }, [localCarts, registrationStatus]);

    const stockOf = (item) => {
        if (!item || item._optimistic) return Infinity;
        return Number(item.available_stock ?? item.product?.quantity ?? Infinity);
    };

    function updateQuantity(id, newQuantity, { optimistic = true } = {}) {
        // 1. INSTANT local UI update (optimistic)
        if (optimistic) {
            setLocalCarts((prev) => prev.map((c) => c.id === id ? { ...c, quantity: newQuantity } : c));
        }

        // 2. Debounce DB call (300ms) — rapid clicks ko merge kar dega
        if (debounceTimers.current[id]) {
            clearTimeout(debounceTimers.current[id]);
        }

        pendingUpdates.current[id] = newQuantity;

        debounceTimers.current[id] = setTimeout(() => {
            const finalQty = pendingUpdates.current[id];
            delete pendingUpdates.current[id];

            toastIdRef.current[id] = toast.loading("Updating quantity...", {
                id: toastIdRef.current[id], // reuse same toast
            });

            axios
                .put("/admin/cart/update-quantity", { id, quantity: finalQty })
                .then((res) => {
                    playSound(SuccessSound);
                    toast.success(res?.data?.message || "Quantity updated", {
                        id: toastIdRef.current[id], // replace loading toast
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
                    setCartUpdated((prev) => !prev);
                })
                .finally(() => {
                    setEditingQuantity((prev) => ({ ...prev, [id]: false }));
                });
        }, 300);
    }

    function handleQuantityBlur(id, value) {
        const item = localCarts.find((cart) => cart.id === id);
        if (!item || item._optimistic) {
            setEditingQuantity((prev) => ({ ...prev, [id]: false }));
            return;
        }
        const isAccessory = !!item?.parent_cart_id || !!item?.is_accessory;
        const stockEnforced = quantityValidation == "disabled"; // disabled = enforce stock
        const newQuantity = parseFloat(value);

        if (isNaN(newQuantity) || newQuantity < 0) {
            setEditingQuantity((prev) => ({ ...prev, [id]: false }));
            return;
        }

        // Block only when: main product AND stock is being enforced AND exceeds stock
        if (!isAccessory && stockEnforced && newQuantity > stockOf(item)) {
            playSound(WarningSound);
            toast.error(`Only ${stockOf(item)} items available in stock`);
            setEditingQuantity((prev) => ({ ...prev, [id]: false }));
            return;
        }

        updateQuantity(id, newQuantity);
    }

    function increment(id) {
        const item = localCarts.find((cart) => cart.id === id);
        if (!item || item._optimistic) return;

        const isAccessory = !!item.parent_cart_id || !!item.is_accessory;
        const stockEnforced = quantityValidation == "disabled";

        const currentQty = Number(item.quantity);

        if (isAccessory || !stockEnforced || currentQty < stockOf(item)) {
            updateQuantity(id, currentQty + 1);
        }
    }

    function decrement(id) {
        const item = localCarts.find((cart) => cart.id === id);
        if (!item || item._optimistic || item.quantity <= 1) return;
        const newQty = (Number(item.quantity) - 1).toFixed(2);
        updateQuantity(id, parseFloat(newQty));

    }

    function destroy(id) {
        const item = localCarts.find((cart) => cart.id === id);
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
                        toast.error(err.response.data.message);
                    });
            }
        });
    }

    const shouldApplyFurtherTax = (item) => {
        if (!item || !item.product || !registrationStatus) return false;
        return (
            item.product.sale_type?.trim().toLowerCase() !== "3rd schedule goods" && registrationStatus.toLowerCase() === "unregistered"
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

                                            return (
                                                <React.Fragment key={item.id}>
                                                    {/* ── PARENT ROW ── */}
                                                    <tr>
                                                        <td>
                                                            {item._optimistic ? (
                                                                <span className="text-muted"><i className="fas fa-spinner fa-spin"></i> Adding...</span>
                                                            ) : (
                                                                <strong>{item.product.name}</strong>
                                                            )}
                                                            {item.variation_label && (
                                                                <div className="small text-muted">{item.variation_label}</div>
                                                            )}
                                                            {itemAccessories.length > 0 && (
                                                                <span
                                                                    className="badge badge-info ml-1"
                                                                    style={{ fontSize: "0.7em" }}
                                                                    title={`${itemAccessories.length} accessory item(s)`}
                                                                >
                                                                    <i className="fas fa-puzzle-piece"></i>{" "}
                                                                    {itemAccessories.length}
                                                                </span>
                                                            )}
                                                        </td>

                                                        <td className="d-flex align-items-center">
                                                            <button
                                                                className="btn btn-warning btn-sm"
                                                                onClick={() => decrement(item.id)}
                                                                disabled={item._optimistic || item.quantity <= 1}
                                                            >
                                                                <i className="fas fa-minus"></i>
                                                            </button>

                                                            {editingQuantity[item.id] ? (
                                                                <input
                                                                    type="number"
                                                                    className="form-control form-control-sm qty ml-1 mr-1"
                                                                    defaultValue={item.quantity}
                                                                    min="0.01"
                                                                    step="0.01"
                                                                    max={stockOf(item)}
                                                                    autoFocus
                                                                    onBlur={(e) =>
                                                                        handleQuantityBlur(item.id, e.target.value)
                                                                    }
                                                                    onKeyPress={(e) => {
                                                                        if (e.key === "Enter") {
                                                                            handleQuantityBlur(item.id, e.target.value);
                                                                        }
                                                                    }}
                                                                    style={{ width: "60px" }}
                                                                />
                                                            ) : (
                                                                <div
                                                                    className="form-control form-control-sm qty ml-1 mr-1 text-center"
                                                                    style={{
                                                                        width: "60px",
                                                                        cursor: "pointer",
                                                                        backgroundColor: "#f8f9fa",
                                                                    }}
                                                                    onClick={() => {
                                                                        if (item._optimistic) return;
                                                                        setEditingQuantity((prev) => ({ ...prev, [item.id]: true, }))
                                                                    }}
                                                                >
                                                                    {item.quantity}
                                                                </div>
                                                            )}

                                                            <button
                                                                className="btn btn-success btn-sm"
                                                                onClick={() => increment(item.id)}
                                                                disabled={item._optimistic ||
                                                                    quantityValidation == "disabled" && item.quantity >= stockOf(item)
                                                                }
                                                            >
                                                                <i className="fas fa-plus"></i>
                                                            </button>
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
                                                                    {/* {parseFloat(item.customer_tax) > 0 && (
                                                                        <div className="small text-primary">
                                                                            +{item.customer_tax} customer tax
                                                                        </div>
                                                                    )} */}
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
                                                                    {/* {parseFloat(item.customer_tax) > 0 && (
                                                                        <div className="small text-primary">
                                                                            +{item.customer_tax} customer tax
                                                                        </div>
                                                                    )} */}
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

            <Toaster position="top-right" reverseOrder={false} />
        </>
    );
}