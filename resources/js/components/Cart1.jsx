import axios from "axios";
import React, { useState, useEffect, useRef } from "react";
import toast, { Toaster } from "react-hot-toast";
import Swal from "sweetalert2";
import SuccessSound from "../sounds/beep-07a.mp3";
import WarningSound from "../sounds/beep-02.mp3";
import playSound from "../utils/playSound";

const PriceInput = ({ item, onPriceChange, onPriceSave }) => {

    const [value, setValue] = useState(item.unit_price ?? item.product.discounted_price ?? "");

    useEffect(() => {
        setValue(item.unit_price ?? item.product.discounted_price ?? "");
    }, [item.id]);


    const handleChange = (e) => {

        const newValue = e.target.value;

        setValue(newValue);

        const price = parseFloat(newValue);

        if (!isNaN(price) && price >= 0) {
            onPriceChange(item.id, price);
        }

    };

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
            onChange={handleChange}
            onBlur={handleBlur}
        />

    );

};

export default function Cart1({
    carts,
    setCartUpdated,
    cartUpdated,
    isRegistered,
    onPriceChange,
    onPriceSave,
    onTotalsChange,
    quantityValidation
}) {
    const [editingQuantity, setEditingQuantity] = useState({});
    const [cartItems, setCartItems] = useState([]);
    const [subTotal, setSubTotal] = useState(0);
    const [furtherTaxTotal, setFurtherTaxTotal] = useState(0);
    const [grandTotal, setGrandTotal] = useState(0);

    const [localCarts, setLocalCarts] = useState(carts);
    const pendingUpdates = useRef({});
    const debounceTimers = useRef({});

    useEffect(() => {
        setLocalCarts((prev) => {
            if (Object.keys(pendingUpdates.current).length === 0) return carts;
            return carts.map((serverItem) => {
                const pendingQty = pendingUpdates.current[serverItem.id];
                return pendingQty !== undefined ? { ...serverItem, quantity: pendingQty } : serverItem;
            });
        });
    }, [carts]);

    const registrationStatus = isRegistered || "Registered";

    useEffect(() => {
        let subTotalCalc = 0;
        let furtherTaxCalc = 0;
        let saleTaxCalc = 0;

        const updatedCarts = localCarts.map((item) => {
            const price = Number(item.unit_price ?? item.product.discounted_price);
            const baseTotal = price * item.quantity;

            const rateText = String(item.product.pos_sale_tax).trim();

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

            // Only parent items contribute to totals
            if (!item.parent_cart_id && !item.is_accessory) {
                subTotalCalc += baseTotal;
                saleTaxCalc += saleTax;
                furtherTaxCalc += furtherTax;
            }

            return {
                ...item,
                base_total: baseTotal.toFixed(2),
                sale_tax: saleTax.toFixed(2),
                further_tax: furtherTax.toFixed(2),
                total_with_tax: (baseTotal + saleTax + furtherTax).toFixed(2),
            };
        });

        setCartItems(updatedCarts);
        setSubTotal(subTotalCalc.toFixed(2));
        setFurtherTaxTotal(furtherTaxCalc.toFixed(2));
        setGrandTotal((subTotalCalc + furtherTaxCalc + saleTaxCalc).toFixed(2));

        if (onTotalsChange) {
            onTotalsChange(
                subTotalCalc,
                saleTaxCalc,
                furtherTaxCalc,
                // customerTaxCalc,
                subTotalCalc + furtherTaxCalc + saleTaxCalc,
            );
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

            axios
                .put("/admin/cart/update-quantity", {
                    id: id,
                    quantity: finalQty,
                })
                .then((res) => {
                    playSound(SuccessSound);
                })
                .catch((err) => {
                    playSound(WarningSound);
                    toast.error(err.response?.data?.message || "Something went wrong");
                    // Rollback: server se fresh carts le lo
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
        updateQuantity(id, item.quantity - 1);
    }

    function destroy(id) {
        const item = localCarts.find((cart) => cart.id === id);
        if (!item || item._optimistic) return;
        Swal.fire({
            title: "Are you sure you want to delete this item?",
            showDenyButton: true,
            confirmButtonText: "Yes",
            denyButtonText: "No",
            customClass: {
                actions: "my-actions",
                cancelButton: "order-1 right-gap",
                confirmButton: "order-2",
                denyButton: "order-3",
            },
        }).then((result) => {
            if (result.isConfirmed) {
                axios
                    .put("/admin/cart/delete", {
                        id: id,
                    })
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

    // Group accessories by parent_cart_id
    const accessoryMap = {};
    cartItems.forEach((item) => {
        if (item.parent_cart_id || item.is_accessory) {
            const pid = item.parent_cart_id;
            if (!accessoryMap[pid]) accessoryMap[pid] = [];
            accessoryMap[pid].push(item);
        }
    });

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
                                                                <span className="badge badge-info ml-1" style={{ fontSize: "0.7em" }}>
                                                                    <i className="fas fa-puzzle-piece"></i> {itemAccessories.length}
                                                                </span>
                                                            )}
                                                        </td>
                                                        <td className="d-flex align-items-center">
                                                            <button className="btn btn-warning btn-sm" onClick={() => decrement(item.id)}
                                                                disabled={item._optimistic || item.quantity <= 1}>
                                                                <i className="fas fa-minus"></i>
                                                            </button>
                                                            {editingQuantity[item.id] ? (
                                                                <input type="number" className="form-control form-control-sm qty ml-1 mr-1"
                                                                    defaultValue={item.quantity} min="0.01" step="0.01" max={stockOf(item)} autoFocus
                                                                    onBlur={(e) => handleQuantityBlur(item.id, e.target.value)}
                                                                    onKeyPress={(e) => { if (e.key === "Enter") handleQuantityBlur(item.id, e.target.value); }}
                                                                    style={{ width: "60px" }} />
                                                            ) : (
                                                                <div className="form-control form-control-sm qty ml-1 mr-1 text-center"
                                                                    style={{ width: "60px", cursor: "pointer", backgroundColor: "#f8f9fa" }}
                                                                    onClick={() => {
                                                                        if (item._optimistic) return;
                                                                        setEditingQuantity((prev) => ({ ...prev, [item.id]: true }))
                                                                    }}>
                                                                    {item.quantity}
                                                                </div>
                                                            )}
                                                            <button className="btn btn-success btn-sm" onClick={() => increment(item.id)}
                                                                disabled={item._optimistic || quantityValidation == "disabled" && item.quantity >= stockOf(item)}>
                                                                <i className="fas fa-plus"></i>
                                                            </button>
                                                        </td>
                                                        <td>
                                                            <button className="btn btn-danger btn-sm mr-3" onClick={() => destroy(item.id)}>
                                                                <i className="fas fa-trash"></i>
                                                            </button>
                                                        </td>
                                                        <td className="text-right">

                                                            <PriceInput item={item} onPriceSave={onPriceSave} />

                                                            {item.product.price > item.product.discounted_price && (
                                                                <>
                                                                    <br />
                                                                    <del>{item.product.price}</del>
                                                                </>
                                                            )}

                                                        </td>
                                                        <td className="text-right">

                                                            <>
                                                                <div className="font-weight-bold">{item.total_with_tax}</div>
                                                                <div className="small text-warning">+{item.sale_tax} sale tax</div>
                                                            </>
                                                        </td>
                                                    </tr>

                                                </React.Fragment>
                                            );
                                        })
                                    ) : (
                                        <tr>
                                            <td colSpan="5" className="text-center">Cart is empty</td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <Toaster position="top-right" reverseOrder={false} />
        </>
    );
}
