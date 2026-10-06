import React, { useEffect, useState, useCallback, useRef } from "react";
import axios from "axios";
import Swal from "sweetalert2";
import Cart from "./Cart";
import toast, { Toaster } from "react-hot-toast";
import CustomerSelect from "./CutomerSelect";
import ProductCreateDigitalModal from "./ProductCreateDigitalModal";
import { ConfirmBox } from "./ConfirmBox";

import SuccessSound from "../sounds/beep-07a.mp3";
import WarningSound from "../sounds/beep-02.mp3";
import playSound from "../utils/playSound";
import VariationModal from "./modals/VariationModal";
import BatchModal from "./modals/BatchModal";

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

export default function Pos({
    draftButtonEnabled,
    customInvoiceNumberEnabled,
    poNumberEnabled,
    fbrEnabled, }) {
    const [isSubmitting, setIsSubmitting] = useState(false);

    const [products, setProducts] = useState([]);
    const [carts, setCarts] = useState([]);
    const [orderDiscount, setOrderDiscount] = useState(0);
    const [paid, setPaid] = useState(0);
    const [due, setDue] = useState(0);
    const [total, setTotal] = useState(0);
    const [updateTotal, setUpdateTotal] = useState(0);
    const [customerId, setCustomerId] = useState();
    const customerIdRef = useRef(); // keeps a non-stale ref for use in callbacks
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
    const [SaleTax, setSaleTax] = useState(0);
    // const [customerTaxTotal, setCustomerTaxTotal] = useState(0);
    const [subTotal, setSubTotal] = useState(0);
    const today = new Date().toISOString().split("T")[0];

    const minDateObj = new Date();
    minDateObj.setDate(minDateObj.getDate() - 30);
    const minDate = minDateObj.toISOString().split("T")[0];

    const [orderDate, setOrderDate] = useState(today);
    const [customInvoiceNumber, setCustomInvoiceNumber] = useState("");
    const [poNumber, setPoNumber] = useState("");

    const [showBatchModal, setShowBatchModal] = useState(false);
    const [showVariationModal, setShowVariationModal] = useState(false);
    const [pendingProduct, setPendingProduct] = useState(null);
    const [availableBatches, setAvailableBatches] = useState([]);
    const [targetProduct, setTargetProduct] = useState(null);
    const [quantityValidation, setQuantityValidation] = useState(null);

    const [showProductModal, setShowProductModal] = useState(false);

    const handleCreateProduct = () => setShowProductModal(true);
    const handleProductCreated = (newProductId) => {
        getUpdatedProducts();
        addProductToCart({ id: newProductId, has_variations: false }, null);
    };

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
        setCarts(prev => prev.map(item => item.id === cartId ? { ...item, unit_price: price } : item));

    };

    const savePriceChange = (cartId, newPrice) => {

        const price = parseFloat(newPrice);
        if (isNaN(price) || price < 0) {
            return;
        }

        axios.put("/admin/cart/update-price", {id: cartId,price: price,})
            .then(() => {
                playSound(SuccessSound);
            })
            .catch(err => {
                console.error("Price update failed", err);
                setCartUpdated(prev => !prev);
            });

    };



    // Sync customerId into ref whenever it changes (fixes stale closure in callbacks)
    useEffect(() => {
        customerIdRef.current = customerId;
    }, [customerId]);

    // Called by CustomerSelect when customer changes
    const emptyCartOnCustomerChange = async () => {
        try {
            await axios.put("/admin/cart/empty"); // backend empty cart
            setCarts([]); // frontend cart clear
            setCartUpdated(prev => !prev);
            playSound(SuccessSound);
            toast.success("Cart emptied due to customer change");
        } catch (err) {
            playSound(WarningSound);
            toast.error("Failed to empty cart");
            console.error(err);
        }
    };


    const fullDomainWithPort = `${protocol}//${hostname}${port ? `:${port}` : ""}`;
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
                    const matched = productsData.data[0];
                    const scannedVariation = matched.has_variations
                        ? (matched.variations || []).find((v) => v.sku && v.sku === barcode)
                        : null;

                    if (matched.has_variations && !scannedVariation) {
                        setPendingProduct(matched);
                        setShowVariationModal(true);
                    } else {
                        addProductToCart(matched, null, null, scannedVariation?.id ?? null);
                    }
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
            const res = await axios.get("/admin/digitalCart/data");
            const data = res.data;
            // setTotal(data?.total);
            // setUpdateTotal(data?.total - orderDiscount);
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
        let paid1 = paid;
        let disc = orderDiscount;
        if (paid == "") {
            paid1 = 0;
        }
        if (orderDiscount == "") {
            disc = 0;
        }
        const updatedTotalAmount = parseFloat(total) - parseFloat(disc);
        const dueAmount = updatedTotalAmount - parseFloat(paid1);
        setUpdateTotal(updatedTotalAmount?.toFixed(2));
        setDue(dueAmount?.toFixed(2));
    }, [orderDiscount, paid, total]);
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

    function addProductToCart(product, customerId, purchaseItemId = null, variationId = null) {
        // Use ref as fallback so this works immediately after customer selection
        const resolvedCustomerId = customerId || customerIdRef.current;
        if (!resolvedCustomerId) {
            toast.error("Please Select Customer");
            return;
        }

        const id = product.id;

        // Step 1: variable product with no variation chosen yet -> ask which one
        if (product.has_variations && !variationId) {
            if (!product.variations || product.variations.length === 0) {
                toast.error("This product has no active variations.");
                return;
            }
            setPendingProduct(product);
            setShowVariationModal(true);
            return;
        }

        if (!purchaseItemId) {
            const url = variationId ? `/admin/product/${id}/batches/${variationId}` : `/admin/product/${id}/batches`;
            axios
                .get(url)
                .then((res) => {
                    const batches = res.data.batches;
                    const targetInfo = {
                        id: res.data.product_id,
                        name: res.data.variation_label
                            ? `${res.data.product_name} – ${res.data.variation_label}`
                            : res.data.product_name,
                        variationId: res.data.variation_id ?? null,
                    };

                    if (batches.length <= 0) {
                        toast.error("Insufficient stock or no batches found.");
                        return;
                    }

                    const batchEnabled = res.data.is_batch_enabled === true || res.data.is_batch_enabled == 1;

                    if (!batchEnabled) {
                        submitToCart(id, null, variationId);
                    } else if (batches.length === 1) {
                        submitToCart(id, batches[0].id, variationId);
                    } else {
                        // Show selection modal
                        setTargetProduct(targetInfo);
                        setAvailableBatches(batches);
                        setShowBatchModal(true);
                    }
                })
                .catch((err) => {
                    toast.error(err?.response?.data?.message || "Failed to fetch product batches.");
                });
        } else {
            submitToCart(id, purchaseItemId, variationId);
        }
    }

    // Variation picked in the modal -> continue the same flow with variationId set
    const handleVariationSelect = (variation) => {
        setShowVariationModal(false);
        if (!pendingProduct) return;
        addProductToCart(pendingProduct, null, null, variation.id);   // was (pendingProduct, null, variation.id)
        setPendingProduct(null);
    };

    function submitToCart(productId, purchaseItemId, variationId = null) {
        // Optimistic update: find an existing cart item for this product
        // and temporarily increment its quantity so the UI updates instantly
        setCarts((prev) => {
            const same = (c) =>
                c.product?.id === productId &&
                c.purchase_item_id === purchaseItemId &&
                (c.product_variation_id ?? null) === (variationId ?? null);

            if (prev.find(same)) {
                return prev.map((c) => (same(c) ? { ...c, quantity: c.quantity + 1 } : c));
            }
            return [
                ...prev,
                {
                    id: `optimistic-${productId}-${variationId ?? 0}-${Date.now()}`,
                    product: { id: productId, name: "Loading...", discounted_price: 0, price: 0, rate_of_tax: "0", sale_type: "" },
                    product_variation_id: variationId,
                    quantity: 1,
                    purchase_item_id: purchaseItemId,
                    unit_price: null,
                    _optimistic: true,
                },
            ];
        });

        axios
            .post("/admin/cart", {
                id: productId,
                product_variation_id: variationId,
                purchase_item_id: purchaseItemId,
            })
            .then((res) => {
                // Replace optimistic state with real server data
                setCartUpdated((prev) => !prev);
                playSound(SuccessSound);
                toast.success(res?.data?.message);
                setShowBatchModal(false);
            })
            .catch((err) => {
                // Rollback optimistic update on error
                setCartUpdated((prev) => !prev);
                playSound(WarningSound);
                toast.error(err.response?.data?.message || "Failed to add product");
            });
    }

    async function cartEmpty() {
        if (total <= 0) return;

        const result = await ConfirmBox({
            title: "Are you sure you want to clear the cart?",
            subtitle: "All products will be removed from the cart.",
            confirmText: "Clear Cart",
            denyText: "Cancel",
            icon: "trash",
        });
        if (result !== "confirm") return;

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
        const result = await ConfirmBox({
            title: "Are you sure you want to checkout this order?",
            subtitle: fbrEnabled
                ? "Choose how you want to process this order."
                : "Choose an action for this order.",
            confirmText: fbrEnabled ? "Send to FBR" : "Create",
            denyText: "Cancel",
            extraButton: { text: "Save only" },
            fbrEnabled,
        });

        if (result === "confirm") {
            orderCreate("submitted");
        } else if (result === "extra") {
            orderCreate("draft");
        }
        // "deny" or null → do nothing
    };

    const handleSaveDraft = async () => {
        const result = await ConfirmBox({
            title: "Are you sure you want to save this order as a draft?",
            subtitle: "The order will be saved and can be completed later.",
            confirmText: "Save Draft",
            denyText: "Cancel",
            icon: "save",
        });
        if (result !== "confirm") return;
        orderCreate("draft");
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
            "/admin/order/createDigital",
            {
                customer_id: customerId,
                is_registered: isRegistered,
                order_discount: parseFloat(orderDiscount) || 0,
                paid: parseFloat(paid) || 0,
                cart: carts,
                check_duplicate: true,
                orderDate: orderDate,
                order_stage: orderStage,
                custom_invoice_number: customInvoiceNumber,
                po_number: poNumber,
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
        const computedSubTotal = subTotalCalc + furtherTaxCalc;
        setSubTotal(computedSubTotal.toFixed(2));
        setSaleTax(saleTaxCalc.toFixed(2));
        // setCustomerTaxTotal(customerTaxCalc.toFixed(2));
        setTotal(grandTotalCalc);
        setUpdateTotal((grandTotalCalc - orderDiscount).toFixed(2));
        setDue((grandTotalCalc - orderDiscount - paid).toFixed(2));
        setFurtherTaxTotal(furtherTaxCalc.toFixed(2));
    };


    function extractFbrErrorMessage(fbr) {
        if (!fbr) return null;

        // Case: no validationResponse at all — auth/credential type errors
        if (fbr.fault) {
            return fbr.fault.description || fbr.fault.message || "FBR request failed";
        }

        const validation = fbr.validationResponse;
        if (!validation) return null;

        // Case: top-level error message present
        if (validation.error) {
            return validation.error;
        }

        // Case: error is empty at top level, but present per line item
        if (Array.isArray(validation.invoiceStatuses)) {
            const itemError = validation.invoiceStatuses.find((s) => s.error);
            if (itemError) {
                return `Item ${itemError.itemSNo}: ${itemError.error}`;
            }
        }

        return validation.status || "FBR validation failed";
    }


    function createOrder(redirect = false, orderStage = "submitted") {
        axios.put(
            "/admin/order/createDigital",
            {
                customer_id: customerId,
                is_registered: isRegistered, // ✅ IMPORTANT: Send registration status
                order_discount: parseFloat(orderDiscount) || 0,
                paid: parseFloat(paid) || 0,
                cart: carts,
                orderDate: orderDate,
                order_stage: orderStage,
                custom_invoice_number: customInvoiceNumber,
                po_number: poNumber,
            },
            { headers: { "Content-Type": "application/json" } }
        )
            .then((res) => {
                setCartUpdated((prev) => !prev);
                setProductUpdated((prev) => !prev);
                const fbr = res?.data?.fbr_api_response;

                if (orderStage === "draft") {
                    toast.success("Order saved as draft!", { duration: 4000 });
                }
                // FBR disabled
                else if (!fbrEnabled) {
                    toast.success(res?.data?.message || "Order completed successfully!", { duration: 4000 });
                }
                // FBR enabled and submitted
                else {
                    const fbrValidation = fbr?.validationResponse;
                    if (fbrValidation?.statusCode === "00") {
                        toast.success("Order completed successfully!", { duration: 4000 });
                    } else {
                        const errorMessage = extractFbrErrorMessage(fbr);
                        toast.error(errorMessage || "Order Could Not Be Posted On FBR Right Now.", { duration: 9000 });
                    }
                }

                const orderId = res?.data?.order?.id;

                if (!orderId) {
                    // toast.error("Order ID not found, cannot redirect.");
                    return;
                } else {
                    // FBR enabled → show invoice
                    window.location.href = `/admin/orders/pos-invoice-digital/${orderId}`;
                }

                console.log("API Full Response:", res.data);
            })
            .catch((err) => {
                console.error(err);
                const backendMsg = err?.response?.data?.error_message || err?.response?.data?.error;
                toast.error(backendMsg || "Order Could Not Be Posted On FBR Right Now.", { duration: 6000 });
                setCartUpdated((prev) => !prev);
                setProductUpdated((prev) => !prev);
            })
            .finally(() => {
                setIsSubmitting(false); // enable button again
            });

    }

    return (
        <>
            <div className="mt-n5 mb-3 d-flex justify-content-end">
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
                {(customInvoiceNumberEnabled || poNumberEnabled) && (
                    <div className="col-md-4">

                        {customInvoiceNumberEnabled && (
                            <Field label="Invoice No.">
                                <input
                                    type="text"
                                    className="form-control form-control-sm"
                                    value={customInvoiceNumber}
                                    onChange={(e) =>
                                        setCustomInvoiceNumber(e.target.value)
                                    }
                                    placeholder="Custom Invoice Number"
                                />
                            </Field>
                        )}

                        {poNumberEnabled && (
                            <Field label="PO No.">
                                <input
                                    type="text"
                                    className="form-control form-control-sm"
                                    value={poNumber}
                                    onChange={(e) =>
                                        setPoNumber(e.target.value)
                                    }
                                    placeholder="PO Number"
                                />
                            </Field>
                        )}

                    </div>
                )}
            </div>
            <div className="card">

                <div className="card-body p-2 p-md-4 pt-0">
                    <div className="row">
                        <div className="col-md-6 col-lg-5 mb-2">
                            <div className="row mb-2">
                                <div className="col-12">
                                    <CustomerSelect
                                        setCustomerId={setCustomerId}
                                        setIsRegistered={setIsRegistered}
                                        // setCustomerTaxType={setCustomerTaxType}
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
                            <Cart
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
                                            {/* {Number(total).toFixed(2)} */}
                                            {subTotal}
                                        </div>
                                    </div>
                                    <div className="row text-bold mb-1">
                                        <div className="col">Sale Tax:</div>
                                        <div className="col text-right mr-2">{Number(SaleTax).toFixed(2)}</div>
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
                                            {updateTotal}
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
                                {draftButtonEnabled && fbrEnabled && (
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
                                {/* FIRST CARD → ADD PRODUCT */}
                                <div className="col-6 col-md-4 col-lg-3 mb-3">
                                    <div
                                        className="text-center add-product-card"
                                        style={{
                                            cursor: "pointer",
                                            border: "2px dashed #ccc",
                                            padding: "20px",
                                            height: "100%"
                                        }}
                                        onClick={() => handleCreateProduct()} // your function
                                    >
                                        <div className="d-flex flex-column justify-content-center align-items-center h-100">
                                            <h2 style={{ fontSize: "30px", marginBottom: "10px" }}>+</h2>
                                            <p className="mb-0 fw-bold">Add Product</p>
                                        </div>
                                    </div>
                                </div>
                                {products.length > 0 &&
                                    products.map((product, index) => (
                                        <div onClick={() => addProductToCart(product)} style={{ cursor: "pointer" }}
                                            className="col-6 col-md-4 col-lg-3 mb-3" key={index}>
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
                                                        {product.has_variations && (
                                                            <span className="badge badge-info ml-1" style={{ fontSize: "0.65em" }}>
                                                                Options
                                                            </span>
                                                        )}
                                                    </p>
                                                    <p>Price: {product?.discounted_price}</p>
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
            <VariationModal
                show={showVariationModal}
                product={pendingProduct}
                quantityValidation={quantityValidation}
                onClose={() => {
                    setShowVariationModal(false);
                    setPendingProduct(null);
                }}
                onSelect={handleVariationSelect}
            />
            <BatchModal
                show={showBatchModal}
                product={targetProduct}
                batches={availableBatches}
                quantityValidation={quantityValidation}
                onClose={() => setShowBatchModal(false)}
                onSelect={(batch) =>
                    submitToCart(
                        targetProduct.id,
                        batch.id,
                        targetProduct.variationId
                    )
                }
            />

            <ProductCreateDigitalModal
                show={showProductModal}
                onClose={() => setShowProductModal(false)}
                onCreated={handleProductCreated}
            />

            <Toaster position="top-right" reverseOrder={false} />
        </>
    );
}

