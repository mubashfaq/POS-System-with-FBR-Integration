import React, { useEffect, useState, useCallback, useRef } from "react";
import axios from "axios";
import Swal from "sweetalert2";
import Cart1 from "./Cart1";
import toast, { Toaster } from "react-hot-toast";
import CustomerSelect from "./CutomerSelect";
import { ConfirmBox } from "./ConfirmBox";

import SuccessSound from "../sounds/beep-07a.mp3";
import WarningSound from "../sounds/beep-02.mp3";
import playSound from "../utils/playSound";

const Field = ({ label, required, children, alignStart }) => (
    <div
        className={`d-flex ${alignStart ? "align-items-start" : "align-items-center"} mb-3`}
    >
        <label
            style={{ width: "100px", flexShrink: 0, fontSize: 14, textAlign: "center" }}
        >
            {label}
            {required && <span className="text-danger">*</span>}
        </label>
        <div style={{ flex: 1, minWidth: 0 }}>{children}</div>
    </div>
);

export default function Pos({ businessType, draftButtonEnabled }) {
    const [isSubmitting, setIsSubmitting] = useState(false);

    const [products, setProducts] = useState([]);
    const [carts, setCarts] = useState([]);
    const [orderDiscount, setOrderDiscount] = useState(0);
    const [paid, setPaid] = useState(0);
    const [due, setDue] = useState(0);
    const [total, setTotal] = useState(0);
    const [updateTotal, setUpdateTotal] = useState(0);
    const [customerId, setCustomerId] = useState();
    const customerIdRef = useRef(undefined); // ✅ always reflects latest customerId (fixes stale closure)
    const [isRegistered, setIsRegistered] = useState("Registered");
    // const [customerTaxType, setCustomerTaxType] = useState(null);
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
    // const [customerTaxTotal, setCustomerTaxTotal] = useState(0);

    const [showBatchModal, setShowBatchModal] = useState(false);
    const [availableBatches, setAvailableBatches] = useState([]);
    const [targetProduct, setTargetProduct] = useState(null);
    const [quantityValidation, setQuantityValidation] = useState(null);
    // ✅ Opt-4: Double-click guard — tracks product IDs currently being added
    const [addingProducts, setAddingProducts] = useState(new Set());

    const today = new Date().toISOString().split("T")[0];

    const minDateObj = new Date();
    minDateObj.setDate(minDateObj.getDate() - 15);
    const minDate = minDateObj.toISOString().split("T")[0];

    const [orderDate, setOrderDate] = useState(today);

    useEffect(() => {
        axios.get("/admin/cart/quantity-validation")
            .then((res) => setQuantityValidation(res.data.quantity_validation))
            .catch(() => setQuantityValidation("enabled")); // safe fallback — no stock enforcement
    }, []); // empty array = runs once on mount

    const handlePriceChange = (cartId, newPrice) => {

        const price = parseFloat(newPrice);

        // Don't update if invalid
        if (isNaN(price) || price < 0) {
            return;
        }

        // Update React cart state immediately
        setCarts(prev =>
            prev.map(item =>
                item.id === cartId
                    ? {
                        ...item,
                        unit_price: price
                    }
                    : item
            )
        );

    };

    const savePriceChange = (cartId, newPrice) => {

        const price = parseFloat(newPrice);

        if (isNaN(price) || price < 0) {
            return;
        }

        axios.put("/admin/cart/update-price", {
            id: cartId,
            price: price,
        })
            .then(() => {

                setCartUpdated(prev => !prev);

            })
            .catch(err => {

                console.error("Price update failed", err);

            });

    };

    const api = (path) => {
        const prefix = businessType === "pral"
            ? "/admin/pral"
            : "/admin";

        return `${prefix}${path}`;
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


    const fullDomainWithPort = `${protocol}//${hostname}${port ? `:${port}` : ""
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
                    addProductToCart(productsData.data[0].id, customerIdRef.current); // ✅ use ref for barcode scan
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
            const res = await axios.get(api("/cart"));;
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
    // ✅ Opt-2: Debounce product name search — wait 300ms after last keystroke
    useEffect(() => {
        setSearchBarcode("");
        if (!searchQuery) return;
        const timer = setTimeout(() => {
            setProducts([]);
            getProducts(searchQuery, currentPage, "");
        }, 300);
        return () => clearTimeout(timer); // cleanup on next keystroke
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

    // ✅ Opt-3: useCallback so the function reference is stable across renders
    //           Uses customerIdRef so barcode-scan path also gets the latest customer
    const addProductToCart = useCallback((id, _customerIdArg, purchaseItemId = null) => {
        const cid = customerIdRef.current; // always fresh — no stale closure
        if (!cid) {
            toast.error("Please Select Customer");
            return;
        }

        // ✅ Opt-4: Block duplicate clicks for the same product
        if (addingProducts.has(id)) return;
        setAddingProducts(prev => new Set(prev).add(id));

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
                        setAddingProducts(prev => { const s = new Set(prev); s.delete(id); return s; });
                        return;
                    }

                    const batchEnabled = res.data.is_batch_enabled === true || res.data.is_batch_enabled == 1;

                    if (!batchEnabled) {
                        submitToCart(id, null);
                    } else {
                        if (batches.length === 1) {
                            submitToCart(id, batches[0].id);
                        } else {
                            // Show batch selection modal — release lock so user can retry
                            setAddingProducts(prev => { const s = new Set(prev); s.delete(id); return s; });
                            setTargetProduct(product);
                            setAvailableBatches(batches);
                            setShowBatchModal(true);
                        }
                    }
                })
                .catch(() => {
                    setAddingProducts(prev => { const s = new Set(prev); s.delete(id); return s; });
                    toast.error("Failed to fetch product batches.");
                });
        } else {
            submitToCart(id, purchaseItemId);
        }
    }, [addingProducts]); // addingProducts in deps so guard reads latest Set

    function submitToCart(productId, purchaseItemId) {
        // ✅ Opt-1: Optimistic UI — push a temporary placeholder into the cart immediately
        const tempId = `temp-${Date.now()}`;
        setCarts(prev => [
            ...prev,
            {
                id: tempId,
                _isOptimistic: true,
                product: { id: productId, name: 'Adding...', discounted_price: 0 },
                quantity: 1,
                unit_price: 0,
                further_tax: 0,
            }
        ]);

        axios
            .post("/admin/cart", {
                id: productId,
                purchase_item_id: purchaseItemId,
            })
            .then((res) => {
                // Remove optimistic placeholder, then pull real cart from server
                setCarts(prev => prev.filter(c => c.id !== tempId));
                setCartUpdated(prev => !prev); // triggers getCarts() to refresh with real data
                playSound(SuccessSound);
                toast.success(res?.data?.message);
                setShowBatchModal(false);
            })
            .catch((err) => {
                // Rollback: remove the placeholder on failure
                setCarts(prev => prev.filter(c => c.id !== tempId));
                playSound(WarningSound);
                toast.error(err?.response?.data?.message ?? "Failed to add product.");
            })
            .finally(() => {
                // ✅ Opt-4: Release double-click guard regardless of outcome
                setAddingProducts(prev => { const s = new Set(prev); s.delete(productId); return s; });
            });
    }

    async function cartEmpty() {
        if (total <= 0) return;

        const ok = await ConfirmBox({ title: "Are you sure you want to delete Cart?" });
        if (!ok) return;

        axios
            .put("/admin/cart/empty")
            .then((res) => {
                setCartUpdated(prev => !prev);
                playSound(SuccessSound);
                toast.success(res?.data?.message);
            })
            .catch((err) => {
                playSound(WarningSound);
                toast.error(err.response.data.message);
            });
    }

    const handleCheckout = async () => {
        const ok = await ConfirmBox({ title: "Are you sure you want to checkout this order?" });
        if (ok) orderCreate("submitted");
    };

    const handleSaveDraft = async () => {
        const ok = await ConfirmBox({ title: "Are you sure you want to save this order as a draft?" });
        if (ok) orderCreate("draft");
    };


    function orderCreate(orderStage = "submitted") {
        if (isSubmitting) return;

        if (carts.length === 0) {
            toast.error("Cart is empty");
            return;
        }

        if (!customerId) {
            toast.error("Please select customer");
            return;
        }

        setIsSubmitting(true);

        axios.put(
            api("/order/create"),
            {
                customer_id: customerId,
                is_registered: isRegistered,
                order_discount: parseFloat(orderDiscount) || 0,
                paid: parseFloat(paid) || 0,
                cart: carts,
                check_duplicate: true,
                orderDate: orderDate,
                order_stage: orderStage,
            },
            {
                headers: {
                    "Content-Type": "application/json"
                }
            }
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
                            createOrder(true, orderStage);
                        } else {
                            toast.info("Order not created.");
                            setIsSubmitting(false);
                        }
                    });
                } else {
                    createOrder(true, orderStage);
                }
            })
            .catch((err) => {
                console.error(err);

                const message =
                    err?.response?.data?.message ||
                    "Something went wrong while checking order.";

                toast.error(message);
                setIsSubmitting(false);
            });
    }


    const handleCartTotals = (subTotalCalc, saleTaxCalc, furtherTaxCalc, grandTotalCalc) => {
        // setTotal(grandTotalCalc); // ✅ include further tax
        setTotal(subTotalCalc); // ✅ include further tax
        setSaleTaxTotal(saleTaxCalc);
        // setCustomerTaxTotal(customerTaxCalc.toFixed(2));
        setUpdateTotal(grandTotalCalc - orderDiscount); // after discount
        setDue(grandTotalCalc - orderDiscount - paid); // due
        setFurtherTaxTotal(furtherTaxCalc); // optional display
    };



    function createOrder(redirect = false, orderStage = "submitted") {
        const cartForOrder = carts.map(item => ({
            id: item.id,
            product_id: item.product.id,
            quantity: item.quantity,
            further_tax: item.further_tax,
            price: item.product.discounted_price, // ✅ frontend updated price
        }));
        axios.put(
            api("/order/create"),
            {
                customer_id: customerId,
                is_registered: isRegistered, // ✅ IMPORTANT: Send registration status
                order_discount: parseFloat(orderDiscount) || 0,
                paid: parseFloat(paid) || 0,
                cart: carts,
                orderDate: orderDate,
                order_stage: orderStage,
            },
            { headers: { "Content-Type": "application/json" } }
        )
            .then((res) => {
                console.log("Order Creation Response:", res);
                setCartUpdated(prev => !prev); // ✅ functional updater
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
                {businessType === 'pral' && (
                    <div class="mt-n5 mb-3 d-flex justify-content-end">
                        <div className="col-md-4">
                            <Field label="Date" required>
                                <input
                                    type="date"
                                    className="form-control form-control-sm"
                                    value={orderDate}
                                    min={minDate}
                                    max={today}
                                    onChange={(e) => setOrderDate(e.target.value)}
                                    onKeyDown={(e) => e.preventDefault()}
                                />
                            </Field>
                        </div>
                    </div>
                )}


                <div className="card-body p-2 p-md-4 pt-0">
                    <div className="row">
                        <div className="col-md-6 col-lg-5 mb-2">
                            <div className="row mb-2">
                                <div className="col-12">
                                    <CustomerSelect
                                        setCustomerId={(id) => { setCustomerId(id); customerIdRef.current = id; }} // ✅ keep ref in sync
                                        // setCustomerTaxType={setCustomerTaxType}
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
                                // customerTaxType={customerTaxType}
                                onPriceChange={handlePriceChange}
                                onPriceSave={savePriceChange}
                                onTotalsChange={handleCartTotals}
                                quantityValidation={quantityValidation}
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
                                    {/* {customerTaxTotal > 0 && (
                                        <div className="row text-bold mb-1">
                                            <div className="col">Customer Tax:</div>
                                            <div className="col text-right mr-2">
                                                {Number(customerTaxTotal).toFixed(2)}
                                            </div>
                                        </div>
                                    )} */}
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
                                            {Number(due).toFixed(2)}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div className="row">
                                {/* Clear Cart */}
                                <div className="col">
                                    <button
                                        onClick={cartEmpty}
                                        type="button"
                                        className="btn bg-gradient-danger btn-block text-white text-bold w-100"
                                    >
                                        Clear Cart
                                    </button>
                                </div>

                                {/* Save Draft */}
                                {draftButtonEnabled && (
                                    <div className="col">
                                        <button
                                            onClick={handleSaveDraft}
                                            type="button"
                                            className="btn btn-secondary btn-block text-white text-bold w-100"
                                            disabled={carts.length === 0 || isSubmitting}
                                        >
                                            {isSubmitting ? "Processing..." : "Save Draft"}
                                        </button>
                                    </div>
                                )}

                                {/* Checkout */}
                                <div className="col">
                                    <button
                                        onClick={handleCheckout}
                                        type="button"
                                        className="btn checkout-btn btn-block text-white text-bold w-100"
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
                                                addProductToCart(product.id, customerIdRef.current) // ✅ use ref — never stale
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
                                                        {product.name.length > 18
                                                            ? `${product.name.substring(0, 18)}...`
                                                            : product.name}{" "}
                                                        ({product.quantity})
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
                            {quantityValidation === 'enabled' && (
                                <div className="px-3 pb-2 mt-n4">
                                    <small className="text-muted">
                                        <i className="fas fa-info-circle mr-1"></i>
                                        Batches with less than 1 quantity available can also be selected.
                                    </small>
                                </div>
                            )}
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




