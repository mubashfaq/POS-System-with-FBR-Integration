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
    onPriceChange,
    onTotalsChange,
}) {
    const [editingQuantity, setEditingQuantity] = useState({});
    const [cartItems, setCartItems] = useState([]);
    const [subTotal, setSubTotal] = useState(0);
    const [furtherTaxTotal, setFurtherTaxTotal] = useState(0);
    const [grandTotal, setGrandTotal] = useState(0);

    // Cart.jsx ke top me ya component function ke andar

    const registrationStatus = isRegistered || "Registered";

    useEffect(() => {
        let subTotalExTax = 0;
        let furtherTaxCalc = 0;
        let saleTaxCalc = 0;
    
        const updatedCarts = carts.map((item) => {
            const price = Number(item.unit_price ?? item.product.discounted_price);
            const baseTotal = price * item.quantity;
            const rateText = String(item.product.rate_of_tax).trim();
    
            let saleTax = 0;
            let exTaxAmount = baseTotal;
    
            if (rateText.endsWith("%")) {
                const rate = parseFloat(rateText.replace("%", ""));
                exTaxAmount = baseTotal / (1 + rate / 100);
                saleTax = baseTotal - exTaxAmount;
            } else {
                const taxValue = parseFloat(rateText) || 0;
                saleTax = taxValue;
                exTaxAmount = baseTotal - taxValue;
            }
    
            let furtherTax = 0;
            if (
                item.product.sale_type?.trim().toLowerCase() !== "3rd schedule goods" &&
                registrationStatus?.trim().toLowerCase() === "unregistered"
            ) {
                furtherTax = exTaxAmount * (4 / 100);
            }
    
            subTotalExTax += exTaxAmount;
            saleTaxCalc += saleTax;
            furtherTaxCalc += furtherTax;
    
            return {
                ...item,
                base_total: exTaxAmount.toFixed(2),
                sale_tax: saleTax.toFixed(2),
                further_tax: furtherTax.toFixed(2),
                total_with_tax: (exTaxAmount + furtherTax + saleTax).toFixed(2), // ✅ FIXED
            };
        });
    
        const grandTotal = subTotalExTax + saleTaxCalc + furtherTaxCalc;
    
        setCartItems(updatedCarts);
        setSubTotal(subTotalExTax.toFixed(2));
        setFurtherTaxTotal(furtherTaxCalc.toFixed(2));
        setGrandTotal(grandTotal.toFixed(2));
    
        if (onTotalsChange) {
            onTotalsChange(subTotalExTax, saleTaxCalc, furtherTaxCalc, grandTotal);
        }
    }, [carts, registrationStatus]);

    function updateQuantity(id, newQuantity) {
        axios
            .put("/admin/cart/update-quantity", {
                id: id,
                quantity: newQuantity,
                
            })
            .then((res) => {
                setCartUpdated(!cartUpdated);
                playSound(SuccessSound);
                toast.success(res?.data?.message);
            })
            .catch((err) => {
                playSound(WarningSound);
                toast.error(
                    err.response?.data?.message || "Something went wrong",
                );
            })
            .finally(() => {
                setEditingQuantity((prev) => ({ ...prev, [id]: false }));
            });
    }

    function handleQuantityBlur(id, value) {
        const newQuantity = parseInt(value);
        if (!isNaN(newQuantity) && newQuantity >= 1) {
            updateQuantity(id, newQuantity);
        } else {
            setEditingQuantity((prev) => ({ ...prev, [id]: false }));
        }
    }

    function increment(id) {
        const item = carts.find((cart) => cart.id === id);
        if (item && item.quantity < item.product.quantity) {
            updateQuantity(id, item.quantity + 1);
        }
    }

    function decrement(id) {
        const item = carts.find((cart) => cart.id === id);
        if (item && item.quantity > 1) {
            updateQuantity(id, item.quantity - 1);
        }
    }

    function destroy(id) {
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
                        setCartUpdated(!cartUpdated);
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
        if (!item || !item.product || !registrationStatus) {
            return false;
        }
        return (
            item.product.sale_type != "3rd Schedule Goods" &&
            registrationStatus.toLowerCase() === "unregistered"
        );
    };

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
                                    {cartItems.length > 0 ? (
                                        cartItems.map((item) => {
                                            if (!item) return null;

                                            const applyFurtherTax =
                                                shouldApplyFurtherTax(item);

                                            return (
                                                <tr key={item.id}>
                                                    <td>{item.product.name}</td>
                                                    <td className="d-flex align-items-center">
                                                        <button
                                                            className="btn btn-warning btn-sm"
                                                            onClick={() =>
                                                                decrement(
                                                                    item.id,
                                                                )
                                                            }
                                                            disabled={
                                                                item.quantity <=
                                                                1
                                                            }
                                                        >
                                                            <i className="fas fa-minus"></i>
                                                        </button>

                                                        {editingQuantity[
                                                            item.id
                                                        ] ? (
                                                            <input
                                                                type="number"
                                                                className="form-control form-control-sm qty ml-1 mr-1"
                                                                defaultValue={
                                                                    item.quantity
                                                                }
                                                                min="1"
                                                                max={
                                                                    item.product
                                                                        .quantity
                                                                }
                                                                autoFocus
                                                                onBlur={(e) =>
                                                                    handleQuantityBlur(
                                                                        item.id,
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                                onKeyPress={(
                                                                    e,
                                                                ) => {
                                                                    if (
                                                                        e.key ===
                                                                        "Enter"
                                                                    ) {
                                                                        handleQuantityBlur(
                                                                            item.id,
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        );
                                                                    }
                                                                }}
                                                                style={{
                                                                    width: "60px",
                                                                }}
                                                            />
                                                        ) : (
                                                            <div
                                                                className="form-control form-control-sm qty ml-1 mr-1 text-center"
                                                                style={{
                                                                    width: "60px",
                                                                    cursor: "pointer",
                                                                    backgroundColor:
                                                                        "#f8f9fa",
                                                                }}
                                                                onClick={() =>
                                                                    setEditingQuantity(
                                                                        (
                                                                            prev,
                                                                        ) => ({
                                                                            ...prev,
                                                                            [item.id]: true,
                                                                        }),
                                                                    )
                                                                }
                                                            >
                                                                {item.quantity}
                                                            </div>
                                                        )}

                                                        <button
                                                            className="btn btn-success btn-sm"
                                                            onClick={() =>
                                                                increment(
                                                                    item.id,
                                                                )
                                                            }
                                                            disabled={
                                                                item.quantity >=
                                                                item.product
                                                                    .quantity
                                                            }
                                                        >
                                                            <i className="fas fa-plus"></i>
                                                        </button>
                                                    </td>
                                                    <td>
                                                        <button
                                                            className="btn btn-danger btn-sm mr-3"
                                                            onClick={() =>
                                                                destroy(item.id)
                                                            }
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
                                                            // Update parent state
                                                            onPriceChange(item.id, newPrice);
                                                        }
                                                    }}
                                                />


                                                        {item.product.price >
                                                            item.product
                                                                .discounted_price && (
                                                            <>
                                                                <br />
                                                                <del>
                                                                    {
                                                                        item
                                                                            .product
                                                                            .price
                                                                    }
                                                                </del>
                                                            </>
                                                        )}
                                                    </td>

                                                    {/* <td className="text-right">
                                                        <input
                                                            type="number"
                                                            value={item.unit_price ?? item.product.discounted_price}
                                                            onChange={(e) => onPriceChange(item.id, e.target.value)}
                                                            className="border px-2 w-24 text-right"
                                                        />
                                                        {item.product.price > (item.unit_price ?? item.product.discounted_price) && (
                                                            <>
                                                                <br />
                                                                <del>{item.product.price}</del>
                                                            </>
                                                        )}
                                                    </td> */}

                                                    <td className="text-right">
                                                        {applyFurtherTax ? (
                                                            <>
                                                                <div className="text-success font-weight-bold">
                                                                    {
                                                                        item.total_with_tax
                                                                    }
                                                                </div>
                                                                <div className="small text-muted">
                                                                    <del>
                                                                        {
                                                                            item.base_total
                                                                        }
                                                                    </del>
                                                                </div>
                                                                <div className="small text-danger">
                                                                    +
                                                                    {
                                                                        item.further_tax
                                                                    }{" "}
                                                                    further
                                                                    tax
                                                                </div>
                                                                <div className="small text-warning">+{item.sale_tax} sale tax</div>  {/* ✅ NEW */}
                                                            </>
                                                        ) : (
                                                            <>
                                                                <div className="font-weight-bold">
                                                                    {
                                                                        item.base_total
                                                                    }
                                                                </div>
                                                                <div className="small text-warning">+{item.sale_tax} sale tax</div>  {/* ✅ NEW */}
                                                            </>
                                                        )}
                                                    </td>
                                                </tr>
                                            );
                                        })
                                    ) : (
                                        <tr>
                                            <td
                                                colSpan="5"
                                                className="text-center"
                                            >
                                                Cart is empty
                                            </td>
                                        </tr>
                                    )}

                                    {/* Summary Row */}
                                    
                                </tbody>
                            </table>
                        </div>

                        {/* Info Box */}
                        {cartItems.length > 0 &&
                            registrationStatus.toLowerCase() ===
                                "unregistered" && (
                                <div className="alert alert-info mt-3 mb-0">
                                    <small>
                                        <i className="fas fa-info-circle"></i>{" "}
                                        4% further tax is applied on{" "}
                                        <strong>3rd Schedule Goods</strong> for
                                        unregistered customers.
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
