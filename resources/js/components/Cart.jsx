import axios from "axios";
import React, { useState } from "react";
import toast, { Toaster } from "react-hot-toast";
import Swal from "sweetalert2";
import SuccessSound from "../sounds/beep-07a.mp3";
import WarningSound from "../sounds/beep-02.mp3";
import playSound from "../utils/playSound";

export default function Cart({ carts, setCartUpdated, cartUpdated }) {
    const [editingQuantity, setEditingQuantity] = useState({});

function updateQuantity(id, newQuantity) {
    axios.put("/admin/cart/update-quantity", {
        id: id,
        quantity: newQuantity
    })
    .then((res) => {
        setCartUpdated(!cartUpdated); // refresh parent data
        playSound(SuccessSound);
        toast.success(res?.data?.message);
    })
    .catch((err) => {
        playSound(WarningSound);
        toast.error(err.response?.data?.message || "Something went wrong");
    })
    .finally(() => {
        setEditingQuantity(prev => ({...prev, [id]: false}));
    });
}


function handleQuantityBlur(id, value) {
    const newQuantity = parseInt(value);
    if (!isNaN(newQuantity) && newQuantity >= 1) {
        updateQuantity(id, newQuantity);
    } else {
        setEditingQuantity(prev => ({...prev, [id]: false}));
    }
}


function increment(id) {
    const item = carts.find(cart => cart.id === id);
    if (item && item.quantity < item.product.quantity) {
        updateQuantity(id, item.quantity + 1);
    }
}

function decrement(id) {
    const item = carts.find(cart => cart.id === id);
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

    // Helper function to round numbers
    function round(value, decimals) {
        return Number(Math.round(value + 'e' + decimals) + 'e-' + decimals);
    }

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
                                    {carts.map((item) => (
                                        <tr key={item.id}>
                                            <td>{item.product.name}</td>
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
                                                        min="1"
                                                        max={item.product.quantity} // Add max attribute
                                                        autoFocus
                                                        onBlur={(e) => handleQuantityBlur(item.id, e.target.value)}
                                                        onKeyPress={(e) => {
                                                            if (e.key === 'Enter') {
                                                                handleQuantityBlur(item.id, e.target.value);
                                                            }
                                                        }}
                                                        style={{ width: '60px' }}
                                                    />
                                                ) : (
                                                    <div 
                                                        className="form-control form-control-sm qty ml-1 mr-1 text-center"
                                                        style={{ 
                                                            width: '60px', 
                                                            cursor: 'pointer',
                                                            backgroundColor: '#f8f9fa'
                                                        }}
                                                        onClick={() => setEditingQuantity(prev => ({...prev, [item.id]: true}))}
                                                    >
                                                        {item.quantity}
                                                    </div>
                                                )}
                                                
                                                <button
                                                    className="btn btn-success btn-sm"
                                                    onClick={() => increment(item.id)}
                                                    disabled={item.quantity >= item.product.quantity} // Disable if max reached
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
                                                {item?.product?.discounted_price}
                                                {item?.product?.price > item?.product?.discounted_price ? (
                                                    <>
                                                        <br />
                                                        <del>
                                                            {item?.product?.price}
                                                        </del>
                                                    </>
                                                ) : (
                                                    ""
                                                )}
                                            </td>
                                            <td className="text-right">
                                                {item?.row_total}
                                            </td>
                                        </tr>
                                    ))}
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