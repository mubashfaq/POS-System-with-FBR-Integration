import React, {
    useEffect,
    useState,
    useCallback,
    useRef,
    useMemo,
} from "react";
import axios from "axios";
import Swal from "sweetalert2";
import CartNew from "./CartNew";
import toast, { Toaster } from "react-hot-toast";
import CustomerSelect from "./CutomerSelect";
import { ConfirmBox } from "./ConfirmBox";

import SuccessSound from "../sounds/beep-07a.mp3";
import WarningSound from "../sounds/beep-02.mp3";
import playSound from "../utils/playSound";
import VariationModal from "./modals/VariationModal";
import BatchModal from "./modals/BatchModal";

const ORANGE = "#fd6802";

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

// ─── Number formatter ───────────────────────────────────────────────────────
const fmt = (n) =>
    Number(n || 0).toLocaleString("en-US", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

export default function PosDigital({
    draftButtonEnabled,
    customFieldButtonEnabled,
    customInvoiceNumberEnabled,
    poNumberEnabled,
    fbrEnabled,
    isSuperAdmin, }) {
    // ── Submission guard ───────────────────────────────────────────────────
    const [isSubmitting, setIsSubmitting] = useState(false);

    // ── Core cart / order state ────────────────────────────────────────────
    const [products, setProducts] = useState([]);
    const [carts, setCarts] = useState([]);
    const [orderDiscount, setOrderDiscount] = useState(0);
    const [paid, setPaid] = useState(0);
    const [due, setDue] = useState(0);
    const [total, setTotal] = useState(0);
    const [updateTotal, setUpdateTotal] = useState(0);
    const [customerId, setCustomerId] = useState();
    const customerIdRef = useRef();
    const [isRegistered, setIsRegistered] = useState("Registered");
    // const [customerTaxType, setCustomerTaxType] = useState(null);
    const [cartUpdated, setCartUpdated] = useState(false);
    const [productUpdated, setProductUpdated] = useState(false);
    const [furtherTaxTotal, setFurtherTaxTotal] = useState(0);
    const [SaleTax, setSaleTax] = useState(0);
    // const [customerTaxTotal, setCustomerTaxTotal] = useState(0);
    const [subTotal, setSubTotal] = useState(0);

    // ── Search / pagination ────────────────────────────────────────────────
    const [searchQuery, setSearchQuery] = useState("");
    const [searchBarcode, setSearchBarcode] = useState("");
    const [currentPage, setCurrentPage] = useState(1);
    const [totalPages, setTotalPages] = useState(0);
    const [loading, setLoading] = useState(false);

    // ── Batch modal ────────────────────────────────────────────────────────
    const [showBatchModal, setShowBatchModal] = useState(false);
    const [showVariationModal, setShowVariationModal] = useState(false);
    const [pendingProduct, setPendingProduct] = useState(null);
    const [availableBatches, setAvailableBatches] = useState([]);
    const [targetProduct, setTargetProduct] = useState(null);
    const [quantityValidation, setQuantityValidation] = useState(null);

    // ── Lovable header fields ──────────────────────────────────────────────
    const today = new Date().toISOString().split("T")[0];

    const minDateObj = new Date();
    minDateObj.setDate(minDateObj.getDate() - 30);
    const minDate = minDateObj.toISOString().split("T")[0];

    const [orderDate, setOrderDate] = useState(today);
    const [notes, setNotes] = useState("");
    const [customInvoiceNumber, setCustomInvoiceNumber] = useState("");
    const [poNumber, setPoNumber] = useState("");


    // ── Product-picker dropdown state (Lovable table) ──────────────────────
    const [openPickerKey, setOpenPickerKey] = useState(null);

    const { protocol, hostname, port } = window.location;
    const fullDomainWithPort = `${protocol}//${hostname}${port ? `:${port}` : ""}`;

    const headerStyle = { backgroundColor: ORANGE, color: "#fff" };

    // ═══════════════════════════════════════════════════════════════════════
    // Effects
    // ═══════════════════════════════════════════════════════════════════════

    // Quantity-validation setting

    useEffect(() => {
        axios
            .get("/admin/cart/quantity-validation")
            .then((res) => setQuantityValidation(res.data.quantity_validation))
            .catch(() => setQuantityValidation("enabled"));
    }, []);

    // Keep customerId ref in sync (avoids stale closures in callbacks)
    useEffect(() => {
        customerIdRef.current = customerId;
    }, [customerId]);

    // Load / reload products when productUpdated toggles
    useEffect(() => {
        getUpdatedProducts();
        axios
            .post("/admin/set-customer-session", {
                is_registered: "Registered",
            })
            .catch((err) => console.error("Could not set session:", err));
    }, [productUpdated]);

    // Load cart on mount and whenever cartUpdated toggles
    useEffect(() => {
        getCarts();
    }, []);
    useEffect(() => {
        getCarts();
    }, [cartUpdated]);

    // Recalculate due / updateTotal when discount, paid, or total changes
    useEffect(() => {
        const paid1 = paid === "" ? 0 : paid;
        const disc = orderDiscount === "" ? 0 : orderDiscount;
        const updatedTotalAmount = parseFloat(total) - parseFloat(disc);
        const dueAmount = updatedTotalAmount - parseFloat(paid1);
        setUpdateTotal(updatedTotalAmount?.toFixed(2));
        setDue(dueAmount?.toFixed(2));
    }, [orderDiscount, paid, total]);

    // Search by name
    useEffect(() => {
        if (searchQuery) {
            setProducts([]);
            getProducts(searchQuery, currentPage, "");
        }
        setSearchBarcode("");
    }, [currentPage, searchQuery]);

    // Search by barcode
    useEffect(() => {
        if (searchBarcode) {
            setProducts([]);
            getProducts("", currentPage, searchBarcode);
        }
    }, [searchBarcode]);

    // Infinite scroll
    useEffect(() => {
        const handleScroll = () => {
            if (
                window.innerHeight + document.documentElement.scrollTop >=
                document.documentElement.offsetHeight
            ) {
                if (currentPage < totalPages) {
                    setCurrentPage((prev) => prev + 1);
                }
            }
        };
        window.addEventListener("scroll", handleScroll);
        return () => window.removeEventListener("scroll", handleScroll);
    }, [currentPage, totalPages]);

    // Close product-picker dropdown on outside click
    useEffect(() => {
        const onClick = (e) => {
            if (!e.target.closest(".pd-dropdown")) {
                setOpenPickerKey(null);
            }
        };
        document.addEventListener("click", onClick);
        return () => document.removeEventListener("click", onClick);
    }, []);

    // ═══════════════════════════════════════════════════════════════════════
    // API helpers
    // ═══════════════════════════════════════════════════════════════════════

    const getProducts = useCallback(
        async (search = "", page = 1, barcode = "") => {
            setLoading(true);
            try {
                const res = await axios.get("/admin/get/products", {
                    params: { search, page, barcode },
                });
                const productsData = res.data;
                setProducts((prev) => [...prev, ...productsData.data]);
                if (productsData.data.length === 1 && barcode !== "") {
                    const matched = productsData.data[0];
                    const scanned = matched.has_variations ? (matched.variations || []).find((v) => v.sku && v.sku === barcode) : null;

                    if (matched.has_variations && !scanned) {
                        setPendingProduct(matched);
                        setShowVariationModal(true);
                    } else {
                        addProductToCart(matched, null, null, scanned?.id ?? null);
                    }
                    getCarts();
                }
                setTotalPages(productsData.meta.last_page);
            } catch (error) {
                console.error("Error fetching products:", error);
            } finally {
                setLoading(false);
            }
        },
        [],
    );

    const getUpdatedProducts = useCallback(async () => {
        try {
            const res = await axios.get("/admin/get/products");
            const productsData = res.data;
            setProducts(productsData.data);
            setTotalPages(productsData.meta.last_page);
        } catch (error) {
            console.error("Error fetching products:", error);
        }
    }, []);

    const getCarts = async () => {
        try {
            const res = await axios.get("/admin/digitalCart");
            const data = res.data;
            setCarts(data?.carts);
        } catch (error) {
            console.error("Error fetching carts:", error);
        }
    };

    // ═══════════════════════════════════════════════════════════════════════
    // Customer helpers
    // ═══════════════════════════════════════════════════════════════════════

    const emptyCartOnCustomerChange = async () => {
        try {
            await axios.put("/admin/cart/empty");
            setCarts([]);
            setCartUpdated((prev) => !prev);
            playSound(SuccessSound);
            toast.success("Cart emptied due to customer change");
        } catch (err) {
            playSound(WarningSound);
            toast.error("Failed to empty cart");
            console.error(err);
        }
    };

    // ═══════════════════════════════════════════════════════════════════════
    // Cart operations
    // ═══════════════════════════════════════════════════════════════════════

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

    const handleQuantityChange = (cartId, newQty) => {
        setCarts((prev) =>
            prev.map((item) =>
                item.id === cartId ? { ...item, quantity: newQty } : item,
            ),
        );
    };

    const handleCartTotals = (
        subTotalCalc,
        saleTaxCalc,
        furtherTaxCalc,
        // customerTaxCalc,
        grandTotalCalc,
    ) => {
        const computedSubTotal = subTotalCalc + furtherTaxCalc;
        setSubTotal(computedSubTotal.toFixed(2));
        setSaleTax(saleTaxCalc.toFixed(2));
        // setCustomerTaxTotal(customerTaxCalc.toFixed(2));
        setTotal(grandTotalCalc);
        setUpdateTotal((grandTotalCalc - orderDiscount).toFixed(2));
        setDue((grandTotalCalc - orderDiscount - paid).toFixed(2));
        setFurtherTaxTotal(furtherTaxCalc.toFixed(2));
    };

    function addProductToCart(product, cId, purchaseItemId = null, variationId = null) {
        const resolvedCustomerId = cId || customerIdRef.current;
        if (!resolvedCustomerId) {
            toast.error("Please Select Customer");
            return;
        }

        if (product.has_variations && !variationId) {
            if (!product.variations?.length) {
                toast.error("This product has no active variations.");
                return;
            }
            setPendingProduct(product);
            setShowVariationModal(true);
            return;
        }

        const id = product.id;

        if (purchaseItemId) {
            submitToCart(id, purchaseItemId, variationId);
            return;
        }

        const url = variationId ? `/admin/product/${id}/batches/${variationId}` : `/admin/product/${id}/batches`;
        axios
            .get(url)
            .then((res) => {
                const batches = res.data.batches;
                const target = {
                    id: res.data.product_id,
                    name: res.data.variation_label ? `${res.data.product_name} – ${res.data.variation_label}` : res.data.product_name,
                    variationId: res.data.variation_id ?? null,
                };
                if (batches.length <= 0) {
                    toast.error("Insufficient stock or no batches found.");
                    return;
                }
                const batchEnabled = res.data.is_batch_enabled === true || res.data.is_batch_enabled == 1;

                if (!batchEnabled) submitToCart(id, null, variationId);
                else if (batches.length === 1) submitToCart(id, batches[0].id, variationId);
                else {
                    setTargetProduct(target);
                    setAvailableBatches(batches);
                    setShowBatchModal(true);
                }
            })
            .catch((err) => toast.error(err?.response?.data?.message || "Failed to fetch product batches."));
    }

    const handleVariationSelect = (variation) => {
        setShowVariationModal(false);
        if (!pendingProduct) return;
        addProductToCart(pendingProduct, null, null, variation.id);
        setPendingProduct(null);
    };

    function submitToCart(productId, purchaseItemId, variationId = null) {
        setCarts((prev) => [
            ...prev,
            {
                id: `optimistic-${productId}-${variationId ?? 0}-${Date.now()}-${Math.random()}`,
                product: { id: productId, name: "...", discounted_price: 0, price: 0, rate_of_tax: "0", sale_type: "" },
                product_variation_id: variationId,
                quantity: 1,
                purchase_item_id: purchaseItemId,
                unit_price: null,
            },
        ]);

        axios
            .post("/admin/cart", {
                id: productId,
                product_variation_id: variationId,
                purchase_item_id: purchaseItemId,
            })
            .then((res) => {
                setCartUpdated((prev) => !prev);
                playSound(SuccessSound);
                toast.success(res?.data?.message);
                setShowBatchModal(false);
            })
            .catch((err) => {
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

    // Order creation
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

        axios
            .put(
                "/admin/order/createDigital",
                {
                    customer_id: customerId,
                    is_registered: isRegistered,
                    order_discount: parseFloat(orderDiscount) || 0,
                    paid: parseFloat(paid) || 0,
                    cart: carts,
                    check_duplicate: true,
                    note: notes,
                    orderDate: orderDate,
                    order_stage: orderStage,
                    custom_invoice_number: customInvoiceNumber,
                    po_number: poNumber,
                },
                { headers: { "Content-Type": "application/json" } },
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
                toast.error(
                    err?.response?.data?.message ||
                    "Something went wrong while checking order.",
                );
                setIsSubmitting(false);
            });
    }

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
        axios
            .put(
                "/admin/order/createDigital",
                {
                    customer_id: customerId,
                    is_registered: isRegistered,
                    order_discount: parseFloat(orderDiscount) || 0,
                    paid: parseFloat(paid) || 0,
                    cart: carts,
                    note: notes,
                    orderDate: orderDate,
                    order_stage: orderStage,
                    custom_invoice_number: customInvoiceNumber,
                    po_number: poNumber,
                },
                { headers: { "Content-Type": "application/json" } },
            )
            .then((res) => {
                setCartUpdated(!cartUpdated);
                setProductUpdated(!productUpdated);
                const fbr = res?.data?.fbr_api_response;

                // Draft order: don't check FBR response
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
                }
                window.location.href = `/admin/orders/pos-invoice-digital/${orderId}`;
                console.log("API Full Response:", res.data);
            })
            .catch((err) => {
                console.error(err);
                const backendMsg = err?.response?.data?.error_message || err?.response?.data?.error;
                toast.error(backendMsg || "Order Could Not Be Posted On FBR  Right Now.", { duration: 6000 });
                setCartUpdated((prev) => !prev);
                setProductUpdated((prev) => !prev);
            })
            .finally(() => {
                setIsSubmitting(false);
            });
    }

    // Product modal handlers
    const handleProductCreated = (newProductId) => {
        getUpdatedProducts();
        addProductToCart({ id: newProductId, has_variations: false }, null);
    };

    return (
        <>
            <div className="p-3" style={{ fontSize: 14 }}>
                {/* ── Top header fields (Lovable layout) ──────────────────── */}
                <div className="row">
                    {/* Left Side */}
                    <div className="col-md-4">
                        {/* Customer */}
                        <Field label="Customer" required>
                            <CustomerSelect
                                setCustomerId={setCustomerId}
                                setIsRegistered={setIsRegistered}
                                emptyCart={emptyCartOnCustomerChange}
                                small
                            />
                        </Field>

                        {/* Invoice Number */}
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
                    </div>

                    {/* Center Side */}
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

                    {/* Right Side */}
                    <div className="col-md-4">
                    </div>
                </div>

                {/* ── Cart table (Lovable layout) ──────────────────────────── */}
                <div className="d-flex align-items-center mb-2 mt-2">
                    <span className="me-2">▾</span>
                    <h6 className="mb-0 fw-bold">Products Details</h6>
                </div>

                <CartNew
                    carts={carts}
                    setCartUpdated={setCartUpdated}
                    cartUpdated={cartUpdated}
                    isRegistered={isRegistered}
                    // customerTaxType={customerTaxType}
                    onPriceChange={handlePriceChange}
                    onPriceSave={savePriceChange}
                    onQuantityChange={handleQuantityChange}
                    onTotalsChange={handleCartTotals}
                    quantityValidation={quantityValidation}
                    products={products}
                    onAddProduct={addProductToCart}
                    customerId={customerId}
                    onProductCreated={handleProductCreated}
                    customFieldButtonEnabled={customFieldButtonEnabled}
                    isSuperAdmin={isSuperAdmin}
                />

                {/* ── Notes + Totals (Lovable layout) ─────────────────────── */}
                <div className="row mt-4">
                    <div className="col-lg-6">
                        <label
                            className="form-label fw-bold"
                            style={{ fontSize: 14 }}
                        >
                            Notes
                        </label>
                        <textarea
                            rows={5}
                            className="form-control form-control-sm"
                            value={notes}
                            onChange={(e) => setNotes(e.target.value)}
                        />
                    </div>

                    <div className="col-lg-6">
                        {/* Sub Total */}
                        <div className="row mb-2 align-items-center">
                            <div
                                className="col-7 text-end"
                                style={{ fontSize: 14 }}
                            >
                                Sub Total
                            </div>
                            <div className="col-5">
                                <input
                                    readOnly
                                    value={fmt(subTotal)}
                                    className="form-control form-control-sm text-end bg-light"
                                />
                            </div>
                        </div>

                        {/* Sale Tax */}
                        <div className="row mb-2 align-items-center">
                            <div
                                className="col-7 text-end"
                                style={{ fontSize: 14 }}
                            >
                                Sale Tax
                            </div>
                            <div className="col-5">
                                <input
                                    readOnly
                                    value={fmt(SaleTax)}
                                    className="form-control form-control-sm text-end bg-light"
                                />
                            </div>
                        </div>

                        {/* Customer Tax */}
                        {/* {customerTaxType && (
                            <div className="row mb-2 align-items-center">
                                <div
                                    className="col-7 text-end"
                                    style={{ fontSize: 14 }}
                                >
                                    Customer Tax
                                </div>
                                <div className="col-5">
                                    <input
                                        readOnly
                                        value={fmt(customerTaxTotal)}
                                        className="form-control form-control-sm text-end bg-light"
                                    />
                                </div>
                            </div>
                        )} */}

                        {/* Discount */}
                        <div className="row mb-2 align-items-center">
                            <div
                                className="col-7 text-end"
                                style={{ fontSize: 14 }}
                            >
                                Discount
                            </div>
                            <div className="col-5">
                                <input
                                    type="number"
                                    className="form-control form-control-sm text-end"
                                    placeholder="0.00"
                                    min={0}
                                    disabled={total <= 0}
                                    value={orderDiscount}
                                    onChange={(e) => {
                                        const value = e.target.value;
                                        if (
                                            parseFloat(value) > total ||
                                            parseFloat(value) < 0
                                        )
                                            return;
                                        setOrderDiscount(value);
                                    }}
                                />
                            </div>
                        </div>

                        {/* Total */}
                        <div className="row mb-2 align-items-center">
                            <div
                                className="col-7 text-end fw-bold"
                                style={{ fontSize: 15 }}
                            >
                                Total
                            </div>
                            <div className="col-5">
                                <input
                                    readOnly
                                    value={fmt(updateTotal)}
                                    className="form-control form-control-sm text-end bg-light fw-bold"
                                    style={{ fontSize: 15 }}
                                />
                            </div>
                        </div>

                        {/* Paid */}
                        <div className="row mb-2 align-items-center">
                            <div
                                className="col-7 text-end"
                                style={{ fontSize: 14 }}
                            >
                                Paid
                            </div>
                            <div className="col-5">
                                <input
                                    type="number"
                                    className="form-control form-control-sm text-end"
                                    placeholder="0.00"
                                    min={0}
                                    disabled={total <= 0}
                                    value={paid}
                                    onChange={(e) => {
                                        const value = e.target.value;
                                        if (
                                            parseFloat(value) < 0 ||
                                            parseFloat(value) > updateTotal
                                        )
                                            return;
                                        setPaid(value);
                                    }}
                                />
                            </div>
                        </div>

                        {/* Due */}
                        <div className="row mb-2 align-items-center">
                            <div
                                className="col-7 text-end fw-bold"
                                style={{ fontSize: 14 }}
                            >
                                Due
                            </div>
                            <div className="col-5">
                                <input
                                    readOnly
                                    value={fmt(due)}
                                    className="form-control form-control-sm text-end bg-light fw-bold"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                {/* ── Action buttons ───────────────────────────────────────── */}
                <div className="row mt-3 mb-4">
                    {/* Clear Cart */}
                    <div className="col-6 col-md-3">
                        <button
                            onClick={cartEmpty}
                            type="button"
                            className="btn btn-block text-white fw-bold w-100"
                            style={{
                                backgroundColor: "#dc3545",
                                borderColor: "#dc3545",
                            }}
                        >
                            Clear Cart
                        </button>
                    </div>

                    {/* Save Draft */}
                    {draftButtonEnabled && fbrEnabled && (
                        <div className="col-6 col-md-3">
                            <button
                                onClick={handleSaveDraft}
                                type="button"
                                className="btn btn-block fw-bold text-white w-100"
                                disabled={carts.length === 0 || isSubmitting}
                                style={{
                                    backgroundColor: "#6c757d",
                                    borderColor: "#6c757d",
                                }}
                            >
                                {isSubmitting ? "Processing…" : "Save Draft"}
                            </button>
                        </div>
                    )}

                    {/* Checkout Digital */}
                    <div className="col-6 col-md-3">
                        <button
                            onClick={handleCheckout}
                            type="button"
                            className="btn checkout-btn btn-block fw-bold text-white w-100"
                            disabled={carts.length === 0 || isSubmitting}
                            style={{
                                backgroundColor: ORANGE,
                                borderColor: ORANGE,
                            }}
                        >
                            {isSubmitting ? "Processing…" : "Checkout Digital"}
                        </button>
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

            <Toaster position="top-right" reverseOrder={false} />
        </>
    );
}
