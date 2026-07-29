import axios from "axios";
import React, { useState, useEffect } from "react";
import toast, { Toaster } from "react-hot-toast";
import Swal from "sweetalert2";
import SuccessSound from "../sounds/beep-07a.mp3";
import WarningSound from "../sounds/beep-02.mp3";
import playSound from "../utils/playSound";

export default function Cart({
    carts,
    setCartUpdated,
    cartUpdated,
    isRegistered,
    customerTaxType,
    onPriceChange,
    onTotalsChange,
    quantityValidation
}) {
    const [editingQuantity, setEditingQuantity] = useState({});
    const [cartItems, setCartItems] = useState([]);
    const [subTotal, setSubTotal] = useState(0);
    const [furtherTaxTotal, setFurtherTaxTotal] = useState(0);
    const [customerTaxTotal, setCustomerTaxTotal] = useState(0);
    const [grandTotal, setGrandTotal] = useState(0);

    const registrationStatus = isRegistered || "Registered";

    useEffect(() => {
        let subTotalExTax = 0;
        let furtherTaxCalc = 0;
        let saleTaxCalc = 0;
        let customerTaxCalc = 0;

        // Only parent items contribute to totals
        const parentCarts = carts.filter((i) => !i.parent_cart_id && !i.is_accessory);

        const updatedCarts = carts.map((item) => {
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

            let customerTax = 0;

            if (customerTaxType === "retailer") {
                customerTax = exTaxAmount * 0.005; // 0.5%
            } else if (customerTaxType === "distributor") {
                customerTax = exTaxAmount * 0.01; // 1%
            }

            // Only accumulate from parent items
            if (!item.parent_cart_id && !item.is_accessory) {
                subTotalExTax += exTaxAmount;
                saleTaxCalc += saleTax;
                furtherTaxCalc += furtherTax;
                customerTaxCalc += customerTax;
            }

            return {
                ...item,
                base_total: exTaxAmount.toFixed(2),
                sale_tax: saleTax.toFixed(2),
                further_tax: furtherTax.toFixed(2),
                customer_tax: customerTax.toFixed(2),
                total_with_tax: (exTaxAmount + furtherTax + saleTax + customerTax).toFixed(2),
            };
        });

        const grandTotal = subTotalExTax + saleTaxCalc + furtherTaxCalc + customerTaxCalc;

        setCartItems(updatedCarts);
        setSubTotal(subTotalExTax.toFixed(2));
        setFurtherTaxTotal(furtherTaxCalc.toFixed(2));
        setGrandTotal(grandTotal.toFixed(2));
        setCustomerTaxTotal(customerTaxCalc.toFixed(2));

        if (onTotalsChange) {
            onTotalsChange(subTotalExTax, saleTaxCalc, furtherTaxCalc, customerTaxCalc, grandTotal);
        }
    }, [carts, registrationStatus]);

    function updateQuantity(id, newQuantity) {
        axios
            .put("/admin/cart/update-quantity", {
                id: id,
                quantity: newQuantity,
            })
            .then((res) => {
                setCartUpdated((prev) => !prev);
                playSound(SuccessSound);
                toast.success(res?.data?.message);
            })
            .catch((err) => {
                playSound(WarningSound);
                toast.error(err.response?.data?.message || "Something went wrong");
            })
            .finally(() => {
                setEditingQuantity((prev) => ({ ...prev, [id]: false }));
            });
    }

    function handleQuantityBlur(id, value) {
        const item = carts.find((cart) => cart.id === id);
        const isAccessory = !!item?.parent_cart_id || !!item?.is_accessory;
        const stockEnforced = quantityValidation == "disabled"; // disabled = enforce stock
        const newQuantity = parseFloat(value);
    
        if (isNaN(newQuantity) || newQuantity < 0) {
            setEditingQuantity((prev) => ({ ...prev, [id]: false }));
            return;
        }
    
        // Block only when: main product AND stock is being enforced AND exceeds stock
        if (!isAccessory && stockEnforced && newQuantity > item.product.quantity) {
            playSound(WarningSound);
            toast.error(`Only ${item.product.quantity} items available in stock`);
            setEditingQuantity((prev) => ({ ...prev, [id]: false }));
            return;
        }
    
        updateQuantity(id, newQuantity);
    }

    // function increment(id) {
    //     const item = carts.find((cart) => cart.id === id);
    //     if (!item) return;
    
    //     const isAccessory = !!item.parent_cart_id || !!item.is_accessory;
    //     const stockEnforced = quantityValidation == "disabled"; // disabled = enforce stock
    
    //     // Accessories: always allow
    //     // Main product + validation enabled: always allow (backend handles it)
    //     // Main product + validation disabled: respect stock limit
    //     if (isAccessory || !stockEnforced || item.quantity < item.product.quantity) {
    //         updateQuantity(id, item.quantity + 1);
    //     }
    // }

    function increment(id) {
        const item = carts.find((cart) => cart.id === id);
        if (!item) return;

        const isAccessory = !!item.parent_cart_id || !!item.is_accessory;
        const stockEnforced = quantityValidation == "disabled";

        const currentQty = Number(item.quantity);

        if (isAccessory || !stockEnforced || currentQty < item.product.quantity) {
            updateQuantity(id, currentQty + 1);
        }
    }

    function decrement(id) {
        const item = carts.find((cart) => cart.id === id);
        if (item && item.quantity > 1) {
            const newQty = (Number(item.quantity) - 1).toFixed(2);
            updateQuantity(id, parseFloat(newQty));
        }
    }

    function destroy(id) {
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
            item.product.sale_type != "3rd Schedule Goods" &&
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

                                            return (
                                                <React.Fragment key={item.id}>
                                                    {/* ── PARENT ROW ── */}
                                                    <tr>
                                                        <td>
                                                            <strong>{item.product.name}</strong>
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
                                                                disabled={item.quantity <= 1}
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
                                                                    max={item.product.quantity}
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
                                                                    onClick={() =>
                                                                        setEditingQuantity((prev) => ({
                                                                            ...prev,
                                                                            [item.id]: true,
                                                                        }))
                                                                    }
                                                                >
                                                                    {item.quantity}
                                                                </div>
                                                            )}

                                                            <button
                                                                className="btn btn-success btn-sm"
                                                                onClick={() => increment(item.id)}
                                                                disabled={quantityValidation == "disabled" && item.quantity >= item.product.quantity}
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
                                                            <input
                                                                type="number"
                                                                className="border text-right"
                                                                style={{ width: "80px" }}
                                                                value={item.unit_price ?? item.product.discounted_price}
                                                                onChange={(e) => {
                                                                    const newPrice = parseFloat(e.target.value);
                                                                    if (!isNaN(newPrice) && newPrice >= 0) {
                                                                        onPriceChange(item.id, newPrice);
                                                                    }
                                                                }}
                                                            />
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
                                                                    {parseFloat(item.customer_tax) > 0 && (
                                                                        <div className="small text-primary">
                                                                            +{item.customer_tax} customer tax
                                                                        </div>
                                                                    )}
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
                                                                    {parseFloat(item.customer_tax) > 0 && (
                                                                        <div className="small text-primary">
                                                                            +{item.customer_tax} customer tax
                                                                        </div>
                                                                    )}
                                                                    <div className="small text-warning">
                                                                        +{item.sale_tax} sale tax
                                                                    </div>
                                                                </>
                                                            )}
                                                        </td>
                                                    </tr>

                                                    {/* ── ACCESSORY SUB-ROWS ── */}
                                                    {/* {itemAccessories.map((acc) => (
                                                        <tr
                                                            key={acc.id}
                                                            style={{
                                                                backgroundColor: "#f9f9f9",
                                                                borderLeft: "3px solid #6c757d",
                                                            }}
                                                        >
                                                            <td style={{ paddingLeft: "28px", color: "#6c757d" }}>
                                                                <i
                                                                    className="fas fa-angle-right mr-1"
                                                                    style={{ fontSize: "0.8em" }}
                                                                ></i>
                                                                <em>{acc.product.name}</em>
                                                                <span
                                                                    className="badge badge-secondary ml-1"
                                                                    style={{ fontSize: "0.65em" }}
                                                                >
                                                                    accessory
                                                                </span>
                                                            </td>

                                                            <td className="d-flex align-items-center">
                                                                <button
                                                                    className="btn btn-warning btn-sm"
                                                                    onClick={() => decrement(acc.id)}
                                                                    disabled={acc.quantity <= 1}
                                                                >
                                                                    <i className="fas fa-minus"></i>
                                                                </button>

                                                                {editingQuantity[acc.id] ? (
                                                                    <input
                                                                        type="number"
                                                                        className="form-control form-control-sm qty ml-1 mr-1"
                                                                        defaultValue={acc.quantity}
                                                                        min="1"
                                                                        autoFocus
                                                                        onBlur={(e) =>
                                                                            handleQuantityBlur(acc.id, e.target.value)
                                                                        }
                                                                        onKeyPress={(e) => {
                                                                            if (e.key === "Enter") {
                                                                                handleQuantityBlur(acc.id, e.target.value);
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
                                                                            backgroundColor: "#f0f0f0",
                                                                        }}
                                                                        onClick={() =>
                                                                            setEditingQuantity((prev) => ({
                                                                                ...prev,
                                                                                [acc.id]: true,
                                                                            }))
                                                                        }
                                                                    >
                                                                        {acc.quantity}
                                                                    </div>
                                                                )}

                                                                <button
                                                                    className="btn btn-success btn-sm"
                                                                    onClick={() => increment(acc.id)}
                                                                >
                                                                    <i className="fas fa-plus"></i>
                                                                </button>
                                                            </td>

                                                            <td></td>

                                                            <td className="text-right text-muted">
                                                                <small>—</small>
                                                            </td>

                                                            <td className="text-right text-muted">
                                                                <small>stock only</small>
                                                            </td>
                                                        </tr>
                                                    ))} */}
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