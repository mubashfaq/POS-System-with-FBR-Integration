import React, { useEffect, useState, useCallback } from "react";
import axios from "axios";
import Swal from "sweetalert2";
import Cart1 from "./Cart1";
import toast, { Toaster } from "react-hot-toast";
import CustomerSelect from "./CutomerSelect";

import SuccessSound from "../sounds/beep-07a.mp3";
import WarningSound from "../sounds/beep-02.mp3";
import playSound from "../utils/playSound";

export default function Pos() {
    const [isSubmitting, setIsSubmitting] = useState(false);

    const [products, setProducts] = useState([]);
    const [carts, setCarts] = useState([]);
    const [orderDiscount, setOrderDiscount] = useState(0);
    const [paid, setPaid] = useState(0);
    const [due, setDue] = useState(0);
    const [total, setTotal] = useState(0);
    const [updateTotal, setUpdateTotal] = useState(0);
    const [customerId, setCustomerId] = useState();
    const [isRegistered, setIsRegistered] = useState("Registered"); 
    const [cartUpdated, setCartUpdated] = useState(false);
    const [productUpdated, setProductUpdated] = useState(false);
    const [searchQuery, setSearchQuery] = useState("");
    const [searchBarcode, setSearchBarcode] = useState("");
    const { protocol, hostname, port } = window.location;
    const [currentPage, setCurrentPage] = useState(1);
    const [totalPages, setTotalPages] = useState(0);
    const [loading, setLoading] = useState(false);
    const [furtherTaxTotal, setFurtherTaxTotal] = useState(0);
    const [saleTaxTotal, setSaleTaxTotal] = useState(0);

    const [showBatchModal, setShowBatchModal] = useState(false);
    const [availableBatches, setAvailableBatches] = useState([]);
    const [targetProduct, setTargetProduct] = useState(null);


    const handlePriceChange = (cartId, newPrice) => {
    const price = parseFloat(newPrice);

    if (isNaN(price) || price < 0) return;

     // Update cart state (UI will reflect immediately)
    setCarts(prev =>
        prev.map(item =>
            item.id === cartId
                ? { ...item, unit_price: price }
                : item
        )
    );

    // 2️⃣ Backend API update
    axios.put("/admin/cart/update-price", {
        id: cartId,
        price: price,
    }).then(() => {
        setCartUpdated(prev => !prev);
    }).catch(err => {
        console.error("Price update failed", err);
    });
};

    

    // POS.jsx ke andar, CustomerSelect ke pass karne ke liye
const emptyCartOnCustomerChange = async () => {
    try {
        await axios.put("/admin/cart/empty"); // backend empty cart
        setCarts([]); // frontend cart clear
        setCartUpdated(prev => !prev); // Cart component ko update karne ke liye
        playSound(SuccessSound);
        toast.success("Cart emptied due to customer change");
    } catch (err) {
        playSound(WarningSound);
        toast.error("Failed to empty cart");
        console.error(err);
    }
};


    const fullDomainWithPort = `${protocol}//${hostname}${
        port ? `:${port}` : ""
    }`;
    const getProducts = useCallback(
        async (search = "", page = 1, barcode = "") => {
            setLoading(true);
            try {
                const res = await axios.get("/admin/get/products", {
                    params: { search, page, barcode },
                });
                const productsData = res.data;
                setProducts((prev) => [...prev, ...productsData.data]); // Append new products
                if (productsData.data.length === 1 && barcode != "") {
                    addProductToCart(productsData.data[0].id , customerId);
                    getCarts();
                }
                setTotalPages(productsData.meta.last_page); // Get total pages
            } catch (error) {
                console.error("Error fetching products:", error);
            } finally {
                setLoading(false); // Set loading to false
            }
        },
        []
    );
    const getUpdatedProducts = useCallback(async () => {
        try {
            const res = await axios.get("/admin/get/products");
            const productsData = res.data;
            setProducts(productsData.data);
            setTotalPages(productsData.meta.last_page); // Get total pages
        } catch (error) {
            console.error("Error fetching products:", error);
        }
    }, []);
    useEffect(() => {
        getUpdatedProducts();

        
        // Send to backend
        axios.post('/admin/set-customer-session', {
            is_registered: 'Registered'
        }).catch(err => console.error("Could not set session:", err));

    }, [productUpdated]);

    const getCarts = async () => {
        try {
            const res = await axios.get("/admin/cart");
            const data = res.data;
            setTotal(data?.total);
            setUpdateTotal(data?.total - orderDiscount);
            setCarts(data?.carts);
console.log("CART FROM API", data?.carts);

        } catch (error) {
            console.error("Error fetching carts:", error);
        }
    };

    useEffect(() => {
        getCarts();
    }, []);

    useEffect(() => {
        getCarts();
    }, [cartUpdated]);

    useEffect(() => {
        const paid1 = paid === "" ? 0 : parseFloat(paid);
        const disc = orderDiscount === "" ? 0 : parseFloat(orderDiscount);
    
        // ✅ Calculate grand total from source values, not from updateTotal
        const grandTotal = parseFloat(total) + parseFloat(saleTaxTotal) + parseFloat(furtherTaxTotal);
        const afterDiscount = grandTotal - disc;
        const dueAmount = afterDiscount - paid1;
    
        setUpdateTotal(afterDiscount.toFixed(2));
        setDue(dueAmount.toFixed(2));
    
    }, [orderDiscount, paid, total, saleTaxTotal, furtherTaxTotal]); // ✅ No updateTotal dependency
    useEffect(() => {
        if (searchQuery) {
            setProducts([]);
            getProducts(searchQuery, currentPage, "");
        }
        setSearchBarcode("");
    }, [currentPage, searchQuery]);

    useEffect(() => {
        if (searchBarcode) {
            setProducts([]);
            getProducts("", currentPage, searchBarcode);
        }
    }, [searchBarcode]);

    // Infinite scroll logic
    useEffect(() => {
        const handleScroll = () => {
            if (
                window.innerHeight + document.documentElement.scrollTop >=
                document.documentElement.offsetHeight
            ) {
                // Load next page if not on the last page
                if (currentPage < totalPages) {
                    setCurrentPage((prev) => prev + 1);
                }
            }
        };

        window.addEventListener("scroll", handleScroll);
        return () => {
            window.removeEventListener("scroll", handleScroll);
        };
    }, [currentPage, totalPages]);

    function addProductToCart(id, customerId, purchaseItemId = null) {
        if (!customerId) {
            toast.error("Please Select Customer");
            return;
        }

        if (!purchaseItemId) {
            axios
                .get(`/admin/product/${id}/batches`)
                .then((res) => {
                    const batches = res.data.batches;
                    const product = {
                        id: res.data.product_id,
                        name: res.data.product_name,
                    };

                    if (batches.length === 0) {
                        toast.error("Insufficient stock or no batches found.");
                        return;
                    }

                    if (batches.length === 1) {
                        // Only one batch? Add directly
                        submitToCart(id, batches[0].id);
                    } else {
                        // Multi-batch? Check if batch selection is enabled
                        if (res.data.is_batch_enabled != true || res.data.is_batch_enabled == 0) {
                            // FIFO: Auto-select oldest batch
                            submitToCart(id, batches[batches.length - 1].id);

                        } else {
                            // Show selection modal
                            setTargetProduct(product);
                            setAvailableBatches(batches);
                            setShowBatchModal(true);
                        }
                    }
                })
                .catch((err) => {
                    toast.error("Failed to fetch product batches.");
                });
        } else {
            submitToCart(id, purchaseItemId);
        }
    }

    function submitToCart(productId, purchaseItemId) {
        axios
            .post("/admin/cart", {
                id: productId,
                purchase_item_id: purchaseItemId,
            })
            .then((res) => {
                setCartUpdated(!cartUpdated);
                playSound(SuccessSound);
                toast.success(res?.data?.message);
                setShowBatchModal(false);
            })
            .catch((err) => {
                playSound(WarningSound);
                toast.error(err.response.data.message);
            });
    }
    function cartEmpty() {
        if (total <= 0) {
            return;
        }
        Swal.fire({
            title: "Are you sure you want to delete Cart?",
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
                    .put("/admin/cart/empty")
                    .then((res) => {
                        setCartUpdated(!cartUpdated);
                        playSound(SuccessSound);
                        toast.success(res?.data?.message);
                    })
                    .catch((err) => {
                        playSound(WarningSound);
                        toast.error(err.response.data.message);
                    });
            } else if (result.isDenied) {
                return;
            }
        });
    }
 
function orderCreate() {
    // document.getElementsByClassName("checkout-btn")[0].disabled = true;
    // if (total <= 0) return;
    if (isSubmitting) return;
    if (carts.length === 0) {
        toast.error("Cart is empty");
        return;
    }
    if (!customerId) {
        toast.error("Please select customer");
        return;
    }

    setIsSubmitting(true); // disable button

    // Step 1: Pehle server se duplicate check
    axios.put(
        "/admin/order/create",
        {
            customer_id: customerId,
            is_registered: isRegistered, // ✅ IMPORTANT: Send registration status
            order_discount: parseFloat(orderDiscount) || 0,
            paid: parseFloat(paid) || 0,
            cart: carts,
            check_duplicate: true,
        },
        { headers: { "Content-Type": "application/json" } }
    )
    .then((res) => {
        if (res.data.duplicate) {
            Swal.fire({
                title: res.data.message,
                showDenyButton: true,
                confirmButtonText: "Yes, create it",
                denyButtonText: "No, cancel",
            }).then((result) => {
                if (result.isConfirmed) {
                    createOrder(true);
                    // createOrderWithRedirect();
                    
                } else {
                    toast.info("Order not created.");
                    setIsSubmitting(false);
                }
            });
        } else {
            createOrder(true);
            // createOrderWithRedirect();  
        }
    })
    .catch((err) => {
        console.error(err);
        const message = err?.response?.data?.message || "Something went wrong while checking order.";
        toast.error(message);
        setIsSubmitting(false);
    });
}


const handleCartTotals = (subTotalCalc, saleTaxCalc, furtherTaxCalc, grandTotalCalc) => {
    // setTotal(grandTotalCalc); // ✅ include further tax
    setTotal(subTotalCalc); // ✅ include further tax
    setSaleTaxTotal(saleTaxCalc);
    setUpdateTotal(grandTotalCalc - orderDiscount); // after discount
    setDue(grandTotalCalc - orderDiscount - paid); // due
    setFurtherTaxTotal(furtherTaxCalc); // optional display
};



function createOrder(redirect = false) {
    const cartForOrder = carts.map(item => ({
        id: item.id,
        product_id: item.product.id,
        quantity: item.quantity,
        further_tax: item.further_tax,
        price: item.product.discounted_price, // ✅ frontend updated price
    }));
    axios.put(
        "/admin/order/create",
        {
            customer_id: customerId,
            is_registered: isRegistered, // ✅ IMPORTANT: Send registration status
            order_discount: parseFloat(orderDiscount) || 0,
            paid: parseFloat(paid) || 0,
            cart: carts,
        },
        { headers: { "Content-Type": "application/json" } }
    )
    .then((res) => {
        console.log("Order Creation Response:", res);
        setCartUpdated(!cartUpdated);
        setProductUpdated(!productUpdated);
        const fbr = res?.data?.fbr_api_response;

        // If FBR is disabled, there is no fbr_api_response — order is still successful
        if (!fbr) {
            toast.success(res?.data?.message || "Order completed successfully!", { duration: 4000 });
        } else if (fbr.Code === "100" || fbr.Response === "Invoice received successfully") {
            toast.success("Order completed successfully!", { duration: 4000 });
        } else {
            toast.error("Order Not Created", { duration: 4000 });
        }

        // FBR response check (only when FBR is enabled)
        if (fbr) {
            setTimeout(() => {
                const fbrValidation = fbr?.validationResponse;

                if (!fbrValidation) {
                    return; // No validation response to show
                }

                if (fbrValidation.statusCode === "00") {
                    toast.success(
                        `FBR Invoice Validated\nStatus: ${fbrValidation.status}\nStatus Code: ${fbrValidation.statusCode}`,
                        { duration: 7000 }
                    );
                } else {
                    toast.error(
                        `FBR Validation Failed\nStatus: ${fbrValidation.status}\nMessage: ${fbrValidation.error}`,
                        { duration: 9000 }
                    );
                }
            }, 800);
        }

        // After order API response, redirect to invoice
        const orderId = res?.data?.order?.id;

        if (!orderId) {
            toast.error("Order ID not found, cannot redirect.");
            return;
        } else {
            // FBR enabled → show invoice
            window.location.href = `/admin/orders/pos-invoice/${orderId}`;
        }

        console.log("API Full Response:", res.data);
    })
    .catch((err) => {
        console.error(err);
        toast.error("Order creation failed.");
    })
    .finally(() => {
        setIsSubmitting(false); // enable button again
    });

    // document.getElementsByClassName("checkout-btn")[0].disabled = false;
}

    return (
        <>
            <div className="card">
                {/* <div class="mt-n5 mb-3 d-flex justify-content-end">
                    <a
                        href="/admin"
                        className="btn bg-gradient-primary mr-2"
                    >
                        Dashboard
                    </a>
                    <a
                        href="/admin/ordersma"
                        className="btn bg-gradient-primary"
                    >
                        Orders
                    </a>
                </div> */}

                <div className="card-body p-2 p-md-4 pt-0">
                    <div className="row">
                        <div className="col-md-6 col-lg-5 mb-2">
                            <div className="row mb-2">
                                <div className="col-12">
                                    <CustomerSelect
                                        setCustomerId={setCustomerId}
                                        setIsRegistered={setIsRegistered}
                                        emptyCart={emptyCartOnCustomerChange}
                                    />
                                </div>
                                {/* <div className="col-6">
                                <form className="form">
                                    <input
                                        type="text"
                                        className="form-control"
                                        placeholder="Enter barcode"
                                        value={searchQuery}
                                        onChange={(e) =>
                                            setSearchQuery(e.target.value)
                                        }
                                    />
                                </form>
                            </div> */}
                            </div>
                            <Cart1
                                carts={carts}
                                setCartUpdated={setCartUpdated}
                                cartUpdated={cartUpdated}
                                isRegistered={isRegistered}
                                onPriceChange={handlePriceChange}
                                onTotalsChange={handleCartTotals}
                            />
                            <div className="card">
                                <div className="card-body">
                                    <div className="row text-bold mb-1">
                                        <div className="col">Sub Total:</div>
                                        <div className="col text-right mr-2">
                                            {/* {total} */}
                                            {Number(total).toFixed(2)}
                                        </div>
                                    </div>
                                    <div className="row text-bold mb-1">
                                        <div className="col">Sale Tax:</div>
                                        <div className="col text-right mr-2">
                                            {/* {total} */}
                                            {Number(saleTaxTotal).toFixed(2)}
                                        </div>
                                    </div>
                                    {/* <div className="row text-bold mb-1">
                                        <div className="col">Discount:</div>
                                        <div className="col text-right mr-2">
                                            <input
                                                type="number"
                                                className="form-control form-control-sm"
                                                placeholder="Enter discount"
                                                min={0}
                                                disabled={total <= 0}
                                                value={orderDiscount}
                                                onChange={(e) => {
                                                    const value =
                                                        e.target.value;
                                                    if (
                                                        parseFloat(value) >
                                                            total ||
                                                        parseFloat(value) < 0
                                                    ) {
                                                        return;
                                                    }
                                                    setOrderDiscount(value);
                                                }}
                                            />
                                        </div>
                                    </div> */}
                                    {/* <div className="row text-bold mb-1">
                                        <div className="col">
                                            Apply Fractional Discount:
                                        </div>
                                        <div className="col text-right mr-2">
                                            <input
                                                type="checkbox"
                                                className="form-control-sm"
                                                disabled={total <= 0}
                                                onChange={(e) => {
                                                    if (e.target.checked) {
                                                        const fractionalPart =
                                                            total % 1;
                                                        setOrderDiscount(
                                                            fractionalPart?.toFixed(
                                                                2
                                                            )
                                                        );
                                                    } else {
                                                        setOrderDiscount(0);
                                                    }
                                                }}
                                            />
                                        </div>
                                    </div> */}
                                    <div className="row text-bold mb-1">
                                        <div className="col">Total:</div>
                                        <div className="col text-right mr-2">
                                        {Number(updateTotal).toFixed(2)}
                                        </div>
                                    </div>
                                    <div className="row text-bold mb-1">
                                        <div className="col">Paid:</div>
                                        <div className="col text-right mr-2">
                                            <input
                                                type="number"
                                                className="form-control form-control-sm"
                                                placeholder="Enter paid"
                                                min={0}
                                                disabled={total <= 0}
                                                value={paid}
                                                onChange={(e) => {
                                                    const value =
                                                        e.target.value;
                                                    if (
                                                        parseFloat(value) < 0 ||
                                                        parseFloat(value) >
                                                            updateTotal
                                                    ) {
                                                        return;
                                                    }
                                                    setPaid(value);
                                                }}
                                            />
                                        </div>
                                    </div>
                                    <div className="row text-bold">
                                        <div className="col">Due:</div>
                                        <div className="col text-right mr-2">
                                            {due}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div className="row">
                                <div className="col">
                                    <button
                                        onClick={() => cartEmpty()}
                                        type="button"
                                        className="btn bg-gradient-danger btn-block text-white text-bold"
                                    >
                                        Clear Cart
                                    </button>
                                </div>
                                <div className="col">
                                <button
                                    onClick={orderCreate}
                                    type="button"
                                    className="btn checkout-btn btn-block text-white text-bold"
                                    disabled={carts.length === 0 || isSubmitting}
                                >
                                    {isSubmitting ? "Processing..." : "Checkout"}
                                </button>
                                </div>
                            </div>
                        </div>
                        <div className="col-md-6 col-lg-7">
                            <div className="row">
                                <div className="input-group mb-2 col-md-6">
                                    <div className="input-group-prepend">
                                        <span className="input-group-text">
                                            <i className="fas fa-barcode"></i>
                                        </span>
                                    </div>
                                    <input
                                        type="text"
                                        className="form-control"
                                        placeholder="Enter Product Barcode"
                                        value={searchBarcode}
                                        autoFocus
                                        onChange={(e) =>
                                            setSearchBarcode(e.target.value)
                                        }
                                    />
                                </div>
                                <div className="mb-2 col-md-6">
                                    <input
                                        type="text"
                                        className="form-control"
                                        placeholder="Enter Product Name"
                                        value={searchQuery}
                                        onChange={(e) =>
                                            setSearchQuery(e.target.value)
                                        }
                                    />
                                </div>
                            </div>
                            <div className="row products-card-container">
                                {products.length > 0 &&
                                    products.map((product, index) => (
                                        <div
                                            onClick={() =>
                                                addProductToCart(product.id , customerId)
                                            }
                                            className="col-6 col-md-4 col-lg-3 mb-3"
                                            key={index}
                                            style={{ cursor: "pointer" }}
                                        >
                                            <div className="text-center">
                                                <img
                                                    src={`${fullDomainWithPort}/storage/${product.image}`}
                                                    alt={product.name}
                                                    className="mr-2 img-thumb"
                                                    onError={(e) => {
                                                        e.target.onerror = null;
                                                        e.target.src = `${fullDomainWithPort}/assets/images/no-image.png`;
                                                    }}
                                                    width={120}
                                                    height={100}
                                                />
                                                <div className="product-details">
                                                    <p className="mb-0 text-bold product-name">
                                                        {product.name} (
                                                        {product.quantity})
                                                    </p>
                                                    <p>
                                                        Price:{" "}
                                                        {
                                                            product?.discounted_price
                                                        }
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                            </div>
                            {loading && (
                                <div className="loading-more">
                                    Loading more...
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
            {showBatchModal && (
                <div className="modal show d-block" tabIndex="-1" style={{ backgroundColor: 'rgba(0,0,0,0.5)', overflowY: 'auto' }}>
                    <div className="modal-dialog modal-lg">
                        <div className="modal-content">
                            <div className="modal-header bg-primary text-white">
                                <h5 className="modal-title">Select Batch for {targetProduct?.name}</h5>
                                <button type="button" className="close text-white" onClick={() => setShowBatchModal(false)}>
                                    <span>&times;</span>
                                </button>
                            </div>
                            <div className="modal-body">
                                <div className="table-responsive">
                                    <table className="table table-bordered table-hover">
                                        <thead className="bg-light">
                                            <tr>
                                                <th>Purchase Date</th>
                                                <th>Available Qty</th>
                                                <th>Original Qty</th>
                                                <th>Purchase Price</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {availableBatches.map((batch) => (
                                                <tr key={batch.id}>
                                                    <td>{batch.purchase_date}</td>
                                                    <td className="text-bold text-success">{batch.remaining_qty}</td>
                                                    <td>{batch.quantity}</td>
                                                    <td>{batch.purchase_price}</td>
                                                    <td>
                                                        <button 
                                                            className="btn btn-sm btn-primary"
                                                            onClick={() => addProductToCart(targetProduct.id, customerId, batch.id)}
                                                        >
                                                            Select
                                                        </button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div className="modal-footer">
                                <button type="button" className="btn btn-secondary" onClick={() => setShowBatchModal(false)}>Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
            <Toaster position="top-right" reverseOrder={false} />
        </>
    );
}

const emptyCartOnCustomerChange = async () => {
    await axios.put("/admin/cart/empty");
    setCartUpdated(prev => !prev);
};




