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
                    addProductToCart(productsData.data[0].id, customerId);
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

    // const handleQuantityChange = (cartId, newQty) => {
    //     // Update local state immediately so UI remains responsive
    //     setCarts((prev) =>
    //         prev.map((item) =>
    //             item.id === cartId ? { ...item, quantity: newQty } : item,
    //         ),
    //     );

    //     const qty = parseFloat(newQty);
    //     if (isNaN(qty) || qty <= 0) return;

    //     // Stock validation check if quantityValidation is disabled
    //     const item = carts.find((c) => c.id === cartId);
    //     if (item) {
    //         const isAccessory = !!item.parent_cart_id || !!item.is_accessory;
    //         const stockEnforced = quantityValidation === "disabled";
    //         if (!isAccessory && stockEnforced && qty > item.product.quantity) {
    //             playSound(WarningSound);
    //             toast.error(`Only ${item.product.quantity} items available in stock`);
    //             // Reset to max stock
    //             setCarts((prev) =>
    //                 prev.map((item) =>
    //                     item.id === cartId ? { ...item, quantity: item.product.quantity } : item,
    //                 ),
    //             );
    //             // Sync max stock with backend
    //             axios
    //                 .put("/admin/cart/update-quantity", { id: cartId, quantity: item.product.quantity })
    //                 .then(() => setCartUpdated((prev) => !prev))
    //                 .catch((err) => console.error("Quantity update failed", err));
    //             return;
    //         }
    //     }

    //     axios
    //         .put("/admin/cart/update-quantity", { id: cartId, quantity: qty })
    //         .then(() => setCartUpdated((prev) => !prev))
    //         .catch((err) => console.error("Quantity update failed", err));
    // };

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

    function addProductToCart(id, cId, purchaseItemId = null) {
        const resolvedCustomerId = cId || customerIdRef.current;
        if (!resolvedCustomerId) {
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
                    if (batches.length <= 0) {
                        toast.error("Insufficient stock or no batches found.");
                        return;
                    }

                    const batchEnabled =
                        res.data.is_batch_enabled === true ||
                        res.data.is_batch_enabled === 1;

                    if (!batchEnabled) {
                        submitToCart(id, null);
                    } else {
                        if (batches.length === 1) {
                            submitToCart(id, batches[0].id);
                        } else {
                            setTargetProduct(product);
                            setAvailableBatches(batches);
                            setShowBatchModal(true);
                        }
                    }
                })
                .catch(() => toast.error("Failed to fetch product batches."));
        } else {
            submitToCart(id, purchaseItemId);
        }
    }

    // function submitToCart(productId, purchaseItemId) {
    //     setCarts((prev) => {
    //         const existing = prev.find(
    //             (c) =>
    //                 c.product?.id === productId &&
    //                 c.purchase_item_id === purchaseItemId,
    //         );
    //         if (existing) {
    //             return prev.map((c) =>
    //                 c.product?.id === productId &&
    //                     c.purchase_item_id === purchaseItemId
    //                     ? { ...c, quantity: c.quantity + 1 }
    //                     : c,
    //             );
    //         }
    //         return [
    //             ...prev,
    //             {
    //                 id: `optimistic-${productId}-${Date.now()}`,
    //                 product: {
    //                     id: productId,
    //                     name: "...",
    //                     discounted_price: 0,
    //                     price: 0,
    //                     rate_of_tax: "0",
    //                     sale_type: "",
    //                 },
    //                 quantity: 1,
    //                 purchase_item_id: purchaseItemId,
    //                 unit_price: null,
    //             },
    //         ];
    //     });

    //     axios
    //         .post("/admin/cart", {
    //             id: productId,
    //             purchase_item_id: purchaseItemId,
    //         })
    //         .then((res) => {
    //             setCartUpdated((prev) => !prev);
    //             playSound(SuccessSound);
    //             toast.success(res?.data?.message);
    //             setShowBatchModal(false);
    //         })
    //         .catch((err) => {
    //             setCartUpdated((prev) => !prev);
    //             playSound(WarningSound);
    //             toast.error(
    //                 err.response?.data?.message || "Failed to add product",
    //             );
    //         });
    // }

    function submitToCart(productId, purchaseItemId) {
        setCarts((prev) => [
            ...prev,
            {
                id: `optimistic-${productId}-${Date.now()}-${Math.random()}`,
                product: {
                    id: productId,
                    name: "...",
                    discounted_price: 0,
                    price: 0,
                    rate_of_tax: "0",
                    sale_type: "",
                },
                quantity: 1,
                purchase_item_id: purchaseItemId,
                unit_price: null,
            },
        ]);

        axios
            .post("/admin/cartNewDigital", {
                id: productId,
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
                toast.error(
                    err.response?.data?.message || "Failed to add product",
                );
            });
    }

    async function cartEmpty() {
        if (total <= 0) return;

        const result = await ConfirmBox({ title: "Are you sure you want to delete Cart?" });
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
            confirmText: "Send to FBR",
            denyText: "Cancel",
            extraButton: { text: "Save only" },
        });

        if (result === "confirm") {
            orderCreate("submitted");
        } else if (result === "extra") {
            orderCreate("draft");
        }
        // "deny" or null → do nothing
    };

    const handleSaveDraft = async () => {
        const result = await ConfirmBox({ title: "Are you sure you want to save this order as a draft?" });
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

                if (!fbr) {
                    toast.success(
                        res?.data?.message || "Order completed successfully!",
                        { duration: 4000 },
                    );
                } else {
                    const fbrValidation = fbr?.validationResponse;
                    if (fbrValidation?.statusCode === "00") {
                        toast.success("Order completed successfully!", {
                            duration: 4000,
                        });
                    } else {
                        toast.error("Order Not Created", { duration: 4000 });
                    }
                }

                if (fbr) {
                    setTimeout(() => {
                        const fbrValidation = fbr?.validationResponse;
                        if (!fbrValidation) return;
                        if (fbrValidation.statusCode === "00") {
                            toast.success(
                                `FBR Invoice Validated\nStatus: ${fbrValidation.status}\nStatus Code: ${fbrValidation.statusCode}`,
                                { duration: 7000 },
                            );
                        } else {
                            toast.error(
                                `FBR Validation Failed\nStatus: ${fbrValidation.status}\nMessage: ${fbrValidation.error}`,
                                { duration: 9000 },
                            );
                        }
                    }, 800);
                }

                const orderId = res?.data?.order?.id;
                if (!orderId) {
                    toast.error("Order ID not found, cannot redirect.");
                    return;
                }
                window.location.href = `/admin/orders/pos-invoice-digital/${orderId}`;
                console.log("API Full Response:", res.data);
            })
            .catch((err) => {
                console.error(err);
                toast.error("Order creation failed.");
            })
            .finally(() => {
                setIsSubmitting(false);
            });
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Product modal handlers
    // ═══════════════════════════════════════════════════════════════════════

    const handleProductCreated = (newProductId) => {
        getUpdatedProducts();
        addProductToCart(newProductId, customerId);
    };

    // ═══════════════════════════════════════════════════════════════════════
    // Render
    // ═══════════════════════════════════════════════════════════════════════

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
                    {draftButtonEnabled && (
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

            {/* ── Batch selection modal ──────────────────────────────────── */}
            {showBatchModal && (
                <div
                    className="modal show d-block"
                    tabIndex="-1"
                    style={{
                        backgroundColor: "rgba(0,0,0,0.5)",
                        overflowY: "auto",
                    }}
                >
                    <div className="modal-dialog modal-lg">
                        <div className="modal-content">
                            <div
                                className="modal-header text-white"
                                style={{ backgroundColor: ORANGE }}
                            >
                                <h5 className="modal-title">
                                    Select Batch for {targetProduct?.name}
                                </h5>
                                <button
                                    type="button"
                                    className="close text-white"
                                    onClick={() => setShowBatchModal(false)}
                                >
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
                                                    <td>
                                                        {batch.purchase_date}
                                                    </td>
                                                    <td className="text-bold text-success">
                                                        {batch.remaining_qty}
                                                    </td>
                                                    <td>{batch.quantity}</td>
                                                    <td>
                                                        {batch.purchase_price}
                                                    </td>
                                                    <td>
                                                        <button
                                                            className="btn btn-sm text-white"
                                                            style={{
                                                                backgroundColor:
                                                                    ORANGE,
                                                            }}
                                                            onClick={() =>
                                                                addProductToCart(
                                                                    targetProduct.id,
                                                                    customerId,
                                                                    batch.id,
                                                                )
                                                            }
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
                                <button
                                    type="button"
                                    className="btn btn-secondary"
                                    onClick={() => setShowBatchModal(false)}
                                >
                                    Close
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            <Toaster position="top-right" reverseOrder={false} />
        </>
    );
}
