import axios from "axios";
import React, { useState, useEffect, useRef, useMemo } from "react";
import toast, { Toaster } from "react-hot-toast";
import Swal from "sweetalert2";
import SuccessSound from "../sounds/beep-07a.mp3";
import WarningSound from "../sounds/beep-02.mp3";
import playSound from "../utils/playSound";
import ProductCreateDigitalModal from "./ProductCreateDigitalModal";
import ReactDOM from "react-dom";

const PriceInput = ({ item, onPriceChange, onPriceSave }) => {

    const [value, setValue] = useState(
        item.unit_price ??
        item.product.discounted_price ??
        ""
    );

    useEffect(() => {

        setValue(
            item.unit_price ??
            item.product.discounted_price ??
            ""
        );

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
            className="border text-center"
            style={{ width: 95 }}
            value={value}
            step="any"
            min="0"
            onChange={handleChange}
            onBlur={handleBlur}
        />

    );

};

const QuantityInput = ({ item, onQuantityChange, onQuantityBlur }) => {
    const [value, setValue] = useState(item.quantity ?? "");
    const debounceRef = useRef(null);

    useEffect(() => {
        setValue(item.quantity ?? "");
    }, [item.id]);

    const handleChange = (e) => {
        const newValue = e.target.value;
        setValue(newValue);
        onQuantityChange(item.id, newValue); // live totals, no API call

        clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => {
            onQuantityBlur(item.id, newValue); // save after user stops typing
        }, 1000); // adjust delay as needed
    };

    const handleBlur = () => {
        clearTimeout(debounceRef.current);
        onQuantityBlur(item.id, value); // save immediately if user tabs/clicks away
    };

    useEffect(() => () => clearTimeout(debounceRef.current), []);

    return (
        <input
            type="number"
            min="0.01"
            step="0.01"
            className="form-control form-control-sm text-center"
            style={{ width: 70 }}
            value={value}
            onChange={handleChange}
            onBlur={handleBlur}
        />
    );
};

const ORANGE = "#fd6802";

const fmt = (n) =>
    Number(n || 0).toLocaleString("en-US", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

// Generate a unique key for blank rows
const makeKey = () => `blank-${Math.random().toString(36).slice(2)}`;

export default function Cart({
    carts,
    setCartUpdated,
    cartUpdated,
    isRegistered,
    // customerTaxType,
    onPriceChange,
    onPriceSave,
    onQuantityChange,
    onTotalsChange,
    quantityValidation,
    customFieldButtonEnabled,
    isSuperAdmin,
    products, // full product list for the picker dropdown
    onAddProduct, // fn(productId, customerId) — calls addProductToCart in PosDigital
    customerId, // needed so the picker can add directly
    onProductCreated,
}) {
    const [editingQuantity, setEditingQuantity] = useState({});
    const [cartItems, setCartItems] = useState([]);
    const [subTotal, setSubTotal] = useState(0);
    const [furtherTaxTotal, setFurtherTaxTotal] = useState(0);
    // const [customerTaxTotal, setCustomerTaxTotal] = useState(0);
    const [grandTotal, setGrandTotal] = useState(0);
    const [showProductModal, setShowProductModal] = useState(false);
    const [businessFields, setBusinessFields] = useState([]);
    const activeFields = useMemo(
        () => businessFields.filter((f) => f.is_active),
        [businessFields]
    );
    const [showFieldsModal, setShowFieldsModal] = useState(false);
    const [newFields, setNewFields] = useState([{ field_label: "" }]);

    function addFieldRow() {
        setNewFields((prev) => [...prev, { field_label: "" }]);
    }

    function removeFieldRow(index) {
        setNewFields((prev) => prev.filter((_, i) => i !== index));
    }

    function updateFieldRow(index, value) {
        setNewFields((prev) => prev.map((f, i) => (i === index ? { field_label: value } : f)));
    }

    useEffect(() => {
        axios.get("/admin/business-custom-fields").then((res) => setBusinessFields(res.data));
    }, []);

    // function saveNewField() {
    //     axios.post("/admin/business-custom-fields", {
    //         field_label: newField.field_label,
    //         field_type: newField.field_type,
    //         field_options: newField.field_type === "select"
    //             ? newField.field_options.split(",").map((s) => s.trim()).filter(Boolean)
    //             : null,
    //     }).then((res) => {
    //         setBusinessFields((prev) => [...prev, res.data.field]);
    //         setNewField({ field_label: "", field_type: "text", field_options: "" });
    //         setShowFieldsModal(false);
    //         toast.success("Field added");
    //     }).catch(() => toast.error("Failed to add field"));
    // }

    function saveCustomFieldValue(cartItemId, fieldName, value) {
        setCartItems((prev) =>
            prev.map((it) => it.id === cartItemId
                ? { ...it, custom_fields: { ...(it.custom_fields || {}), [fieldName]: value } }
                : it
            )
        );
        axios.put("/admin/cart/update-custom-fields", {
            id: cartItemId,
            custom_fields: { [fieldName]: value },
        }).then(() => setCartUpdated((prev) => !prev))
            .catch(() => toast.error("Failed to save field"));
    }

    function saveNewFields() {
        const validFields = newFields.filter((f) => f.field_label.trim() !== "");
        if (validFields.length === 0) {
            toast.error("Please enter at least one field label");
            return;
        }

        Promise.all(
            validFields.map((f) =>
                axios.post("/admin/business-custom-fields", {
                    field_label: f.field_label,
                    field_type: "text",
                    field_options: null,
                })
            )
        )
            .then((responses) => {
                const created = responses.map((res) => res.data.field);
                setBusinessFields((prev) => [...prev, ...created]);
                setNewFields([{ field_label: "" }]);
                setShowFieldsModal(false);
                toast.success(`${created.length} field(s) added`);
            })
            .catch((error) => {
                toast.error(error.response?.data?.message || "Failed to add fields");
            });
    }

    function saveCustomFieldValue(cartItemId, fieldName, value) {
        setCartItems((prev) =>
            prev.map((it) => it.id === cartItemId
                ? { ...it, custom_fields: { ...(it.custom_fields || {}), [fieldName]: value } }
                : it
            )
        );
        axios.put("/admin/cart/update-custom-fields", {
            id: cartItemId,
            custom_fields: { [fieldName]: value },
        }).then(() => setCartUpdated((prev) => !prev))
            .catch(() => toast.error("Failed to save field"));
    }

    function toggleFieldActive(id) {
        axios.put(`/admin/business-custom-fields/${id}/toggle`).then((res) => {
            setBusinessFields((prev) =>
                prev.map((f) => (f.id === id ? { ...f, is_active: res.data.field.is_active } : f))
            );
        }).catch(() => toast.error("Failed to update field"));
    }

    const handleProductCreated = (newProductId) => {
        if (onProductCreated) {
            onProductCreated(newProductId);
        }
    };

    // Local-only blank rows (not yet submitted to Laravel)
    const [blankRows, setBlankRows] = useState([]);
    const [openPickerKey, setOpenPickerKey] = useState(null);
    const [pickerSearch, setPickerSearch] = useState({});
    const [pickerPos, setPickerPos] = useState(null); // {top, left, width}
    const dropdownRef = useRef(null);
    const triggerRef = useRef(null);

    const registrationStatus = isRegistered || "Registered";

    // ── Close picker on outside click ──────────────────────────────────────
    useEffect(() => {
        const onClick = (e) => {
            if (showProductModal) return;
            const clickedTrigger = triggerRef.current?.contains(e.target);
            const clickedMenu = dropdownRef.current?.contains(e.target);
            if (!clickedTrigger && !clickedMenu) {
                setOpenPickerKey(null);
            }
        };
        document.addEventListener("mousedown", onClick);
        return () => document.removeEventListener("mousedown", onClick);
    }, [showProductModal]);

    // ── Totals recalc ──────────────────────────────────────────────────────
    useEffect(() => {
        let subTotalExTax = 0;
        let furtherTaxCalc = 0;
        let saleTaxCalc = 0;
        // let customerTaxCalc = 0;

        const updatedCarts = carts.map((item) => {
            const price = Number(
                item.unit_price ?? item.product.discounted_price,
            );
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
                item.product.sale_type?.trim().toLowerCase() !==
                "3rd schedule goods" &&
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

        const gTotal = subTotalExTax + saleTaxCalc + furtherTaxCalc;

        setCartItems(updatedCarts);
        setSubTotal(subTotalExTax.toFixed(2));
        setFurtherTaxTotal(furtherTaxCalc.toFixed(2));
        // setCustomerTaxTotal(customerTaxCalc.toFixed(2));
        setGrandTotal(gTotal.toFixed(2));

        if (onTotalsChange) {
            onTotalsChange(subTotalExTax, saleTaxCalc, furtherTaxCalc, gTotal);
        }
    }, [carts, registrationStatus]);

    // ── Quantity helpers ───────────────────────────────────────────────────
    function updateQuantity(id, newQuantity) {
        axios
            .put("/admin/cart/update-quantity", { id, quantity: newQuantity })
            .then((res) => {
                setCartUpdated((prev) => !prev);
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
        const item = carts.find((cart) => cart.id === id);
        const isAccessory = !!item?.parent_cart_id || !!item?.is_accessory;
        const stockEnforced = quantityValidation == "disabled";
        const newQuantity = parseFloat(value);

        if (isNaN(newQuantity) || newQuantity < 0) {
            setEditingQuantity((prev) => ({ ...prev, [id]: false }));
            return;
        }

        if (
            !isAccessory &&
            stockEnforced &&
            newQuantity > item.product.quantity
        ) {
            playSound(WarningSound);
            toast.error(
                `Only ${item.product.quantity} items available in stock`,
            );
            setEditingQuantity((prev) => ({ ...prev, [id]: false }));
            return;
        }

        updateQuantity(id, newQuantity);
    }

    function increment(id) {
        const item = carts.find((cart) => cart.id === id);
        if (!item) return;
        const isAccessory = !!item.parent_cart_id || !!item.is_accessory;
        const stockEnforced = quantityValidation == "disabled";
        const currentQty = Number(item.quantity);
        if (
            isAccessory ||
            !stockEnforced ||
            currentQty < item.product.quantity
        ) {
            updateQuantity(id, currentQty + 1);
        }
    }

    function decrement(id) {
        const item = carts.find((cart) => cart.id === id);
        if (item && item.quantity > 1) {
            updateQuantity(
                id,
                parseFloat((Number(item.quantity) - 1).toFixed(2)),
            );
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

    // ── Blank row helpers ──────────────────────────────────────────────────
    function addBlankRow() {
        setBlankRows((prev) => [...prev, { key: makeKey() }]);
    }

    function removeBlankRow(key) {
        setBlankRows((prev) => prev.filter((r) => r.key !== key));
        setOpenPickerKey(null);
        setPickerSearch((prev) => {
            const next = { ...prev };
            delete next[key];
            return next;
        });
    }

    // When a product is picked from a blank row's dropdown
    function pickProductForBlankRow(rowKey, product) {
        if (!customerId) {
            toast.error("Please select a customer first");
            return;
        }
        // Remove the blank row immediately
        removeBlankRow(rowKey);
        // Delegate to PosDigital's addProductToCart
        if (onAddProduct) {
            onAddProduct(product.id, customerId);
        }
    }

    // ── Derived data ───────────────────────────────────────────────────────
    const accessoryMap = {};
    cartItems.forEach((item) => {
        if (item.parent_cart_id || item.is_accessory) {
            const pid = item.parent_cart_id;
            if (!accessoryMap[pid]) accessoryMap[pid] = [];
            accessoryMap[pid].push(item);
        }
    });

    const parentItems = cartItems.filter(
        (i) => !i.parent_cart_id && !i.is_accessory,
    );

    const totals = parentItems.reduce(
        (acc, item) => {
            acc.qty += Number(item.quantity) || 0;
            acc.amount += Number(item.base_total) || 0;
            acc.saleTax += Number(item.sale_tax) || 0;
            // acc.customerTax += Number(item.customer_tax) || 0;
            acc.furtherTax += Number(item.further_tax) || 0;
            acc.net += Number(item.total_with_tax) || 0;
            return acc;
        },
        { qty: 0, amount: 0, saleTax: 0, furtherTax: 0, net: 0 },
    );

    const isUnregistered = registrationStatus.toLowerCase() === "unregistered";
    const colSpanFull = (isUnregistered ? 10 : 9) + activeFields.length;
    const headerStyle = { backgroundColor: ORANGE, color: "#fff" };

    return (
        <>
            <div className="table-responsive border rounded">
                <table
                    className="table table-sm mb-0 align-middle"
                    style={{ minWidth: 1100, fontSize: 14 }}
                >
                    <thead>
                        <tr style={headerStyle}>
                            <th className="py-2 pl-3" style={{ minWidth: 220, textAlign: "center" }}>
                                Product / Services
                            </th>
                            {activeFields.map((f) => (
                                <th key={f.id} className="text-center" style={{ minWidth: 110 }}>
                                    {f.field_label}
                                </th>
                            ))}
                            <th className="text-center" style={{ minWidth: 140 }}>
                                Qty
                            </th>
                            <th className="text-center" style={{ minWidth: 110 }}>
                                Rate
                            </th>
                            <th className="text-center" style={{ minWidth: 120 }}>
                                Amount
                            </th>
                            <th className="text-center" style={{ minWidth: 90 }}>
                                Tax Rate
                            </th>
                            <th className="text-center" style={{ minWidth: 100 }}>
                                Sale Tax
                            </th>
                            {isUnregistered && (
                                <th
                                    className="text-right"
                                    style={{ minWidth: 100 }}
                                >
                                    Further Tax
                                </th>
                            )}
                            {/* {customerTaxType && (
                                <th
                                    className="text-center"
                                    style={{ minWidth: 100 }}
                                >
                                    Customer Tax
                                </th>
                            )} */}
                            <th className="text-right" style={{ minWidth: 120 }}>
                                Net
                            </th>
                            <th
                                className="text-center"
                                style={{ minWidth: 70 }}
                            />
                        </tr>
                    </thead>

                    <tbody>
                        {/* ── CART ROWS (from Laravel) ── */}
                        {parentItems.length === 0 && blankRows.length === 0 && (
                            <tr>
                                <td
                                    colSpan={colSpanFull}
                                    className="text-center text-muted py-4"
                                >
                                    No products added. Click "+ Add More" to add
                                    a row.
                                </td>
                            </tr>
                        )}

                        {parentItems.map((item) => {
                            if (!item) return null;
                            const applyFurtherTax = shouldApplyFurtherTax(item);
                            const itemAccessories = accessoryMap[item.id] || [];
                            const rateText = String(item.product.rate_of_tax ?? "0%").trim();

                            return (
                                <React.Fragment key={item.id}>
                                    <tr>
                                        {/* Product name */}
                                        <td className="pl-3">
                                            <strong>{item.product.name}</strong>
                                            {itemAccessories.length > 0 && (
                                                <span
                                                    className="badge badge-info ml-1"
                                                    style={{
                                                        fontSize: "0.7em",
                                                    }}
                                                    title={`${itemAccessories.length} accessory item(s)`}
                                                >
                                                    <i className="fas fa-puzzle-piece"></i>{" "}
                                                    {itemAccessories.length}
                                                </span>
                                            )}
                                        </td>

                                        {activeFields.map((f) => (
                                            <td key={f.id} className="text-center">
                                                {f.field_type === "select" ? (
                                                    <select
                                                        className="form-control form-control-sm"
                                                        style={{ width: 100 }}
                                                        value={item.custom_fields?.[f.field_name] || ""}
                                                        onChange={(e) => saveCustomFieldValue(item.id, f.field_name, e.target.value)}
                                                    >
                                                        <option value="">Select</option>
                                                        {(f.field_options || []).map((opt) => (
                                                            <option key={opt} value={opt}>{opt}</option>
                                                        ))}
                                                    </select>
                                                ) : (
                                                    <input
                                                        type={f.field_type === "number" ? "number" : "text"}
                                                        className="form-control form-control-sm text-center"
                                                        style={{ width: 100 }}
                                                        defaultValue={item.custom_fields?.[f.field_name] || ""}
                                                        onBlur={(e) => saveCustomFieldValue(item.id, f.field_name, e.target.value)}
                                                    />
                                                )}
                                            </td>
                                        ))}

                                        {/* Qty */}
                                        <td>
                                            <div className="d-flex align-items-center justify-content-center">
                                                <QuantityInput
                                                    item={item}
                                                    onQuantityChange={onQuantityChange}
                                                    onQuantityBlur={handleQuantityBlur}
                                                />
                                            </div>
                                        </td>

                                        {/* Rate (editable) */}
                                        <td className="text-right">

                                            <PriceInput
                                                item={item}
                                                onPriceChange={onPriceChange}
                                                onPriceSave={onPriceSave}
                                            />

                                        </td>


                                        {/* Amount */}
                                        <td className="text-center">
                                            {fmt(item.base_total)}
                                        </td>

                                        {/* Tax Rate */}
                                        <td className="text-center">
                                            <span className="badge bg-light text-dark border">
                                                {rateText || "0%"}
                                            </span>
                                        </td>

                                        {/* Sale Tax */}
                                        <td className="text-center fw-semibold">
                                            {fmt(item.sale_tax)}
                                        </td>

                                        {/* Further Tax */}
                                        {isUnregistered && (
                                            <td className="text-center text-danger fw-semibold">
                                                {applyFurtherTax
                                                    ? fmt(item.further_tax)
                                                    : "—"}
                                            </td>
                                        )}

                                        {/* customer Tax */}
                                        {/* {customerTaxType && (
                                            <td className="text-center fw-semibold">
                                                {fmt(item.customer_tax)}
                                            </td>
                                        )} */}

                                        {/* Net */}
                                        <td className="text-right fw-bold">
                                            {fmt(item.total_with_tax)}
                                        </td>

                                        {/* Delete */}
                                        <td className="text-center">
                                            <button
                                                type="button"
                                                className="btn btn-sm btn-link p-0"
                                                style={{ color: ORANGE }}
                                                onClick={() => destroy(item.id)}
                                                title="Remove"
                                            >
                                                🗑
                                            </button>
                                        </td>
                                    </tr>
                                </React.Fragment>
                            );
                        })}

                        {/* ── BLANK ROWS (local only, no product selected yet) ── */}
                        {blankRows.map((row) => {
                            const searchVal = pickerSearch[row.key] || "";
                            const filteredProducts = (products || []).filter(
                                (p) =>
                                    p.name
                                        .toLowerCase()
                                        .includes(searchVal.toLowerCase()),
                            );
                            const isOpen = openPickerKey === row.key;

                            return (
                                <tr
                                    key={row.key}
                                    style={{ backgroundColor: "#fffbf5" }}
                                >
                                    {/* Product picker dropdown */}
                                    <td
                                        className="pl-3"
                                        style={{ minWidth: 220 }}
                                    >
                                        <div className="cart-pd-dropdown position-relative">
                                            <button
                                                type="button"
                                                ref={isOpen ? triggerRef : null}
                                                className="form-control form-control-sm d-flex justify-content-between align-items-center text-left"
                                                style={{ cursor: "pointer" }}
                                                onClick={(e) => {
                                                    e.stopPropagation();
                                                    if (isOpen) {
                                                        setOpenPickerKey(null);
                                                    } else {
                                                        const rect = e.currentTarget.getBoundingClientRect();
                                                        setPickerPos({
                                                            top: rect.bottom,
                                                            left: rect.left,
                                                            width: Math.max(rect.width, 300),
                                                        });
                                                        setOpenPickerKey(row.key);
                                                    }
                                                }}
                                            >
                                                <span className="text-muted">Select product…</span>
                                                <span style={{ color: ORANGE }}>▾</span>
                                            </button>

                                            {isOpen && pickerPos &&
                                                ReactDOM.createPortal(
                                                    <div
                                                        ref={dropdownRef}
                                                        className="cart-pd-dropdown bg-white border rounded shadow"
                                                        style={{
                                                            position: "fixed",
                                                            zIndex: 2000,
                                                            top: pickerPos.top,
                                                            left: pickerPos.left,
                                                            width: pickerPos.width,
                                                        }}
                                                    >
                                                        <div className="p-2 border-bottom">
                                                            <input
                                                                type="text"
                                                                className="form-control form-control-sm"
                                                                placeholder="Search product…"
                                                                autoFocus
                                                                value={searchVal}
                                                                onChange={(e) =>
                                                                    setPickerSearch((prev) => ({
                                                                        ...prev,
                                                                        [row.key]: e.target.value,
                                                                    }))
                                                                }
                                                                onClick={(e) => e.stopPropagation()}
                                                            />
                                                        </div>
                                                        <div style={{ maxHeight: 320, overflowY: "auto" }}>
                                                            {filteredProducts.length === 0 ? (
                                                                <div className="text-center text-muted py-3" style={{ fontSize: 13 }}>
                                                                    No products found
                                                                </div>
                                                            ) : (
                                                                filteredProducts.map((p) => (
                                                                    <button
                                                                        key={p.id}
                                                                        type="button"
                                                                        className="dropdown-item d-flex justify-content-between align-items-center py-2"
                                                                        onClick={() => pickProductForBlankRow(row.key, p)}
                                                                    >
                                                                        <span style={{ fontSize: 13 }}>{p.name}</span>
                                                                        <small className="text-muted ml-2" style={{ whiteSpace: "nowrap" }}>
                                                                            {p.unit} · {fmt(p.discounted_price)}
                                                                        </small>
                                                                    </button>
                                                                ))
                                                            )}
                                                        </div>
                                                    </div>,
                                                    document.body
                                                )
                                            }
                                        </div>
                                    </td>
                                    {activeFields.map((f) => (
                                        <td key={f.id} className="text-center text-muted">
                                            —
                                        </td>
                                    ))}
                                    <td>
                                        <div className="d-flex align-items-center justify-content-center" style={{ gap: "4px" }}>
                                            {/* <button
                                                className="btn btn-warning btn-sm"
                                                disabled
                                                style={{ padding: "1px 7px" }}
                                            >
                                                <i className="fas fa-minus"></i>
                                            </button> */}
                                            <div
                                                className="form-control form-control-sm text-center"
                                                style={{
                                                    width: 70,
                                                    backgroundColor: "#f0f0f0",
                                                }}
                                            >
                                                —
                                            </div>
                                            {/* <button
                                                className="btn btn-success btn-sm"
                                                disabled
                                                style={{ padding: "1px 7px" }}
                                            >
                                                <i className="fas fa-plus"></i>
                                            </button> */}
                                        </div>
                                    </td>
                                    <td>
                                        <input
                                            readOnly
                                            className="form-control form-control-sm text-center bg-light"
                                            style={{ width: 95 }}
                                            value="0.00"
                                        />
                                    </td>
                                    <td className="text-center text-muted">
                                        0.00
                                    </td>
                                    <td className="text-center">
                                        <span
                                            className="badge"
                                            style={{
                                                backgroundColor: "#f0f0f0",
                                                color: "#999",
                                                border: "1px solid #ddd",
                                                fontWeight: 400,
                                            }}
                                        >
                                            —
                                        </span>
                                    </td>
                                    <td className="text-center text-muted">
                                        0.00
                                    </td>
                                    {isUnregistered && (
                                        <td className="text-center text-muted">
                                            —
                                        </td>
                                    )}
                                    {/* {customerTaxType && (
                                        <td className="text-center text-muted">
                                            —
                                        </td>
                                    )} */}
                                    <td className="text-right text-muted">
                                        0.00
                                    </td>

                                    {/* Remove blank row */}
                                    <td className="text-center">
                                        <button
                                            type="button"
                                            className="btn btn-sm btn-link p-0"
                                            style={{ color: ORANGE }}
                                            onClick={() =>
                                                removeBlankRow(row.key)
                                            }
                                            title="Remove row"
                                        >
                                            🗑
                                        </button>
                                    </td>
                                </tr>
                            );
                        })}

                        {/* ── TOTALS ROW ── */}
                        {parentItems.length > 0 && (
                            <tr
                                className="fw-bold"
                                style={{ backgroundColor: "#f5f5f5" }}
                            >
                                <td className="pl-3">Total</td>
                                {activeFields.map((f) => <td key={f.id}></td>)}
                                <td className="text-center">{totals.qty}</td>
                                <td></td>
                                <td className="text-center">
                                    {fmt(totals.amount)}
                                </td>
                                <td></td>
                                <td className="text-center">
                                    {fmt(totals.saleTax)}
                                </td>
                                {isUnregistered && (
                                    <td className="text-center">
                                        {fmt(totals.furtherTax)}
                                    </td>
                                )}
                                {/* {customerTaxType && (
                                    <td className="text-center">
                                        {fmt(totals.customerTax)}
                                    </td>
                                )} */}
                                <td className="text-right">{fmt(totals.net)}</td>
                                <td></td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {/* ── Add More and Add Product buttons ── */}
            <div className="d-flex align-items-center mt-3" style={{ gap: "10px" }}>
                <button
                    type="button"
                    onClick={addBlankRow}
                    className="btn btn-sm rounded-pill"
                    style={{
                        border: `1px solid ${ORANGE}`,
                        color: ORANGE,
                        backgroundColor: "transparent",
                    }}
                    onMouseOver={(e) => {
                        e.currentTarget.style.backgroundColor = ORANGE;
                        e.currentTarget.style.color = "#fff";
                    }}
                    onMouseOut={(e) => {
                        e.currentTarget.style.backgroundColor = "transparent";
                        e.currentTarget.style.color = ORANGE;
                    }}
                >
                    + Add More
                </button>

                <button
                    type="button"
                    onClick={() => setShowProductModal(true)}
                    className="btn btn-sm rounded-pill"
                    style={{
                        border: `1px solid ${ORANGE}`,
                        color: ORANGE,
                        backgroundColor: "transparent",
                    }}
                    onMouseOver={(e) => {
                        e.currentTarget.style.backgroundColor = ORANGE;
                        e.currentTarget.style.color = "#fff";
                    }}
                    onMouseOut={(e) => {
                        e.currentTarget.style.backgroundColor = "transparent";
                        e.currentTarget.style.color = ORANGE;
                    }}
                >
                    + Add Product
                </button>
                {isSuperAdmin && customFieldButtonEnabled && (
                    <button
                        type="button"
                        onClick={() => setShowFieldsModal(true)}
                        className="btn btn-sm rounded-pill"
                        style={{ border: `1px solid ${ORANGE}`, color: ORANGE, backgroundColor: "transparent" }}
                    >
                        + Custom Fields
                    </button>
                )}
            </div>

            {/* Unregistered notice */}
            {cartItems.length > 0 && isUnregistered && (
                <div className="alert alert-info mt-3 mb-0">
                    <small>
                        4% further tax is applied on 3rd Schedule Goods for
                        unregistered customers.
                    </small>
                </div>
            )}

            {/* ── Product create modal ───────────────────────────────────── */}
            <ProductCreateDigitalModal
                show={showProductModal}
                onClose={() => setShowProductModal(false)}
                onCreated={handleProductCreated}
            />

            {showFieldsModal && (
                <div className="modal show d-block" style={{ backgroundColor: "rgba(0,0,0,0.5)" }}>
                    <div className="modal-dialog">
                        <div className="modal-content">
                            <div className="modal-header text-white" style={{ backgroundColor: ORANGE }}>
                                <h5 className="modal-title">Manage Custom Fields</h5>
                                <button className="close text-white" onClick={() => setShowFieldsModal(false)}>&times;</button>
                            </div>
                            <div className="modal-body">
                                <ul className="list-group mb-3">
                                    {businessFields.map((f) => (
                                        <li key={f.id} className="list-group-item d-flex justify-content-between align-items-center">
                                            <span className={f.is_active ? "" : "text-muted"}>{f.field_label}</span>
                                            <div className="custom-control custom-switch">
                                                <input
                                                    type="checkbox"
                                                    className="custom-control-input"
                                                    id={`field-toggle-${f.id}`}
                                                    checked={f.is_active}
                                                    onChange={() => toggleFieldActive(f.id)}
                                                />
                                                <label className="custom-control-label" htmlFor={`field-toggle-${f.id}`}></label>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                                {newFields.map((f, index) => (
                                    <div key={index} className="d-flex mb-2" style={{ gap: 6 }}>
                                        <input
                                            className="form-control form-control-sm"
                                            placeholder="Field label (e.g. Color)"
                                            value={f.field_label}
                                            onChange={(e) => updateFieldRow(index, e.target.value)}
                                        />
                                        {newFields.length > 1 && (
                                            <button
                                                type="button"
                                                className="btn btn-sm btn-outline-danger"
                                                onClick={() => removeFieldRow(index)}
                                            >
                                                &times;
                                            </button>
                                        )}
                                        {index === newFields.length - 1 && (
                                            <button
                                                type="button"
                                                className="btn btn-sm"
                                                style={{ border: `1px solid ${ORANGE}`, color: ORANGE }}
                                                onClick={addFieldRow}
                                            >
                                                +
                                            </button>
                                        )}
                                    </div>
                                ))}
                                {/* <select
                                    className="form-control form-control-sm mb-2"
                                    value={newField.field_type}
                                    onChange={(e) => setNewField({ ...newField, field_type: e.target.value })}
                                >
                                    <option value="text">Text</option>
                                    <option value="number">Number</option>
                                    <option value="select">Select</option>
                                </select>
                                {newField.field_type === "select" && (
                                    <input
                                        className="form-control form-control-sm"
                                        placeholder="Comma separated options e.g. Red,Blue,Green"
                                        value={newField.field_options}
                                        onChange={(e) => setNewField({ ...newField, field_options: e.target.value })}
                                    />
                                )} */}
                            </div>
                            <div className="modal-footer">
                                <button className="btn btn-secondary" onClick={() => setShowFieldsModal(false)}>Close</button>
                                <button className="btn text-white" style={{ backgroundColor: ORANGE }} onClick={saveNewFields}>
                                    Add Field{newFields.filter((f) => f.field_label.trim()).length > 1 ? "s" : ""}
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