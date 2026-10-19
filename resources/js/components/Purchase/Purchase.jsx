import React, { useCallback, useEffect, useState } from "react";
import Suppliers from "./Suppliers";
import axios from "axios";
import Swal from "sweetalert2";
import toast, { Toaster } from "react-hot-toast";
import DatePicker from "react-datepicker";
import "react-datepicker/dist/react-datepicker.css";

export default function Purchase() {
    const [searchTerm, setSearchTerm] = useState("");
    const [barcode, setBarcode] = useState("");
    const [selectedSupplier, setSelectedSupplier] = useState(null);
    const [purchaseId, setPurchaseId] = useState(null);
    const [date, setDate] = useState(null);
    const [supplierId, setSupplierId] = useState(null);
    const [tax, setTax] = useState(0);
    const [discount, setDiscount] = useState(0);
    const [shipping, setShipping] = useState(0);
    const [products, setProducts] = useState([]);
    const [searchResults, setSearchResults] = useState([]);
    const round2 = (n) => parseFloat((Number(n) || 0).toFixed(2));
    const lineKey = (l) => `${l.id}-${l.product_variation_id ?? 0}`;
    useEffect(() => {
        const searchParams = new URLSearchParams(window.location.search);
        const barcodeParam = searchParams.get("barcode");
        const purchase_id = searchParams.get("purchase_id");
        if (barcodeParam) {
            setSearchTerm(barcodeParam);
            setBarcode(barcodeParam);
        }
        if (purchase_id) {
            setPurchaseId(purchase_id);
        }
    }, []);
    useEffect(() => {
        if (barcode) {
            getProducts();
        }
    }, [barcode]);
    useEffect(() => {
        if (purchaseId) {
            getPurchaseProducts();
        }
    }, [purchaseId]);
    const getPurchaseProducts = useCallback(async () => {
        try {
            const res = await axios.get(`/admin/purchase/${purchaseId}`);
            const purchaseData = res.data;
            const purchaseProducts = purchaseData?.items?.map((item) => ({
                item_id: item.id,
                id: item.product_id,
                product_variation_id: item.product_variation_id ?? null,
                name: item.name,
                variation_label: item.variation_label ?? null,
                purchase_price: Number(item.purchase_price),
                price: Number(item.price),
                stock: item.stock,
                qty: Number(item.quantity),
                subTotal: item.purchase_price * item.quantity,
            }));
            console.log(purchaseProducts);
            setProducts(purchaseProducts);
            setDate(purchaseData?.date ? purchaseData.date.split(" ")[0] : "");
            setSelectedSupplier({
                value: purchaseData?.supplier_id,
                label: purchaseData?.supplier?.name,
            });
            setTax(Number(purchaseData?.tax) || 0);
            setDiscount(Number(purchaseData?.discount_value) || 0);
            setShipping(Number(purchaseData?.shipping) || 0);
        } catch (error) {
            console.error("Error fetching products:", error);
        } finally {
        }
    }, [purchaseId]);

    const addLine = (product, variation = null) => {
        const purchasePrice = Number(variation ? variation.purchase_price : product.purchase_price) || 0;

        const line = {
            id: product.id,
            product_variation_id: variation?.id ?? null,
            name: product.name,
            variation_label: variation?.label ?? null,
            price: Number(variation ? variation.price : product.price) || 0,
            purchase_price: purchasePrice,
            stock: variation ? Number(variation.stock ?? 0) : Number(product.quantity ?? 0),
            qty: 1,
            subTotal: purchasePrice,
        };

        setProducts((prev) => {
            const i = prev.findIndex((p) => lineKey(p) === lineKey(line));
            if (i === -1) return [...prev, line];
            return prev.map((p, idx) =>
                idx === i
                    ? { ...p, qty: Number(p.qty) + 1, subTotal: round2(p.purchase_price * (Number(p.qty) + 1)) }
                    : p
            );
        });
    };

    const getProducts = useCallback(async () => {
        const term = searchTerm.trim();
        if (!term) return;

        let needsChoice = false;

        try {
            const res = await axios.get("/admin/products", { params: { search: term } });
            console.log(res);

            (res.data?.data || []).forEach((product) => {
                if (!product.has_variations) {
                    addLine(product);
                    return;
                }
                // A variation SKU scan adds exactly that variation
                const exact = product.variations.find((v) => v.sku && v.sku === term);
                if (exact) {
                    addLine(product, exact);
                } else {
                    needsChoice = true;
                    toast(`Choose a variation for ${product.name} from the list.`);
                }
            });
        } catch (error) {
            console.error("Error fetching products:", error);
        } finally {
            // Keep the list open when the user still has to pick a variation
            if (!needsChoice) setSearchTerm("");
        }
    }, [searchTerm]);

    // Handle deletion of a product
    const handleDelete = (key) => {
        setProducts((prev) => prev.filter((p) => lineKey(p) !== key));
    };

    const handleQtyChange = (key, value) => {
        const newQty = parseInt(value) || 0;
        setProducts((prev) =>
            prev.map((p) =>
                lineKey(p) === key
                    ? { ...p, qty: newQty, subTotal: round2(p.purchase_price * newQty) }
                    : p
            )
        );
    };

    const handlePriceChange = (key, value) => {
        const newPrice = parseFloat(value) || 0;
        setProducts((prev) =>
            prev.map((p) =>
                lineKey(p) === key
                    ? { ...p, purchase_price: newPrice, subTotal: round2(p.qty * newPrice) }
                    : p
            )
        );
    };
    // Add a new product by searching
    const handleSearchAdd = () => {
        getProducts();
    };

    // Calculate totals with two decimal places
    const calculateTotals = () => {
        const subTotal = products.reduce(
            (sum, product) => sum + product.subTotal,
            0
        );
        const formattedSubTotal = parseFloat(parseFloat(subTotal || 0).toFixed(2));
        const formattedTax = parseFloat((tax || 0).toFixed(2));
        const formattedDiscount = parseFloat((discount || 0).toFixed(2));
        const formattedShipping = parseFloat((shipping || 0).toFixed(2));
        const grandTotal = parseFloat(
            (
                formattedSubTotal +
                formattedTax -
                formattedDiscount +
                formattedShipping
            ).toFixed(2)
        );

        return {
            subTotal: formattedSubTotal,
            tax: formattedTax,
            discount: formattedDiscount,
            shipping: formattedShipping,
            grandTotal,
        };
    };

    const totals = calculateTotals();
    const handleSubmit = async () => {
        if (totals.grandTotal <= 0) {
            toast.error("Total must be greater than zero.");
            return;
        }
        if (!date) {
            toast.error("Please select purchase date.");
            return;
        }
        if (!supplierId) {
            toast.error("Please select a supplier.");
            return;
        }

        // Show confirmation dialog
        Swal.fire({
            title: `Are you sure you want to save this purchase?`,
            showDenyButton: true,
            confirmButtonText: "Yes",
            denyButtonText: "No",
            customClass: {
                actions: "my-actions",
                cancelButton: "order-1 right-gap",
                confirmButton: "order-2",
                denyButton: "order-3",
            },
        }).then(async (result) => {
            if (result.isConfirmed) {
                //    console.log("data:", {
                //        products,
                //        supplierId,
                //        totals,
                //    }); return;
                try {
                    const res = await axios.post("/admin/purchase", {
                        purchase_id: purchaseId,
                        date,
                        products,
                        supplierId,
                        totals,
                    });
                    setProducts([]);
                    toast.success(res?.data?.message);
                    window.location.href = "/admin/purchase";
                } catch (err) {
                    toast.error(
                        err.response?.data?.message || "An error occurred"
                    );
                }
            }
        });
    };

    // product search
    useEffect(() => {
        // Define the asynchronous function
        async function getProducts() {
            if (!searchTerm.trim()) {
                setSearchResults([]);
                return;
            }

            try {
                const res = await axios.get("/admin/products", {
                    params: { search: searchTerm },
                });

                const productsData = res.data;
                setSearchResults(productsData?.data || []);
            } catch (error) {
                console.error("Error fetching products:", error);
            }
        }
        // Call the async function inside useEffect
        getProducts();
    }, [searchTerm]);

    // Handle adding selected product to the products list
    const handleProductSelect = (product, variation = null) => {
        addLine(product, variation);
        setSearchTerm("");
        setSearchResults([]);
    };

    const resultLines = searchResults.flatMap((product) =>
        product.has_variations ? product.variations.map((variation) => ({ product, variation })) : [{ product, variation: null }]
    );

    return (
        <>
            <div className="container-fluid">
                <div className="card">
                    <div className="card-body">
                        <div className="row">
                            <div className="mb-3 col-md-6">
                                <label htmlFor="date" className="form-label">
                                    Purchase Date
                                    <span className="text-danger">*</span>
                                </label>
                                <div>
                                    <DatePicker
                                        name="date"
                                        className="form-control"
                                        placeholderText="Enter purchase date"
                                        selected={date}
                                        dateFormat="yyyy-MM-dd"
                                        onChange={(date) => {
                                            const formattedDate = date
                                                ? date
                                                    .toISOString()
                                                    .split("T")[0]
                                                : null;
                                            setDate(formattedDate);
                                        }}
                                    />
                                </div>
                            </div>
                            <div className="mb-3 col-md-6">
                                <label
                                    htmlFor="supplier"
                                    className="form-label"
                                >
                                    Supplier
                                    <span className="text-danger">*</span>
                                </label>
                                <Suppliers
                                    setSupplierId={setSupplierId}
                                    oldSupplier={selectedSupplier}
                                />
                            </div>
                        </div>
                    </div>
                </div>
                <div className="card">
                    <div className="card-body">
                        <div className="row mb-2">
                            <div className="input-group col-6">
                                <div className="input-group-prepend">
                                    <span className="input-group-text">
                                        <i className="fas fa-search"></i>
                                    </span>
                                </div>
                                <input
                                    type="search"
                                    className="form-control form-control-lg"
                                    value={searchTerm}
                                    onChange={(e) =>
                                        setSearchTerm(e.target.value)
                                    }
                                    placeholder="Enter product barcode/name"
                                />
                                <button
                                    className="btn action-button ml-2"
                                    onClick={handleSearchAdd}
                                >
                                    Add Product
                                </button>
                            </div>
                        </div>
                        {/* Display search results below the input */}
                        {resultLines.length > 0 && (
                            <div className="row mb-2">
                                <div className="col-6" style={{ maxHeight: "200px", overflowY: "auto" }}>
                                    <ul className="list-group">
                                        {resultLines.map(({ product, variation }) => (
                                            <li
                                                key={`${product.id}-${variation?.id ?? 0}`}
                                                className="list-group-item d-flex justify-content-between"
                                                style={{ cursor: "pointer" }}
                                                onClick={() => handleProductSelect(product, variation)}
                                            >
                                                <span>
                                                    {product.name}
                                                    {variation && (
                                                        <span className="text-muted"> – {variation.label}</span>
                                                    )}
                                                </span>
                                                <span className="text-muted">
                                                    Stock: {variation ? variation.stock : product.quantity}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            </div>
                        )}
                        <div className="row">
                            <div className="col-12">
                                <table className="table table-sm table-bordered text-center">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Product Name</th>
                                            <th>Purchase Price</th>
                                            <th>Current Stock</th>
                                            <th>Qty</th>
                                            <th>Sub Total</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {products.map((product, index) => (
                                            <tr key={lineKey(product)}>
                                                <td>{index + 1}</td>
                                                <td>
                                                    {product.name}
                                                    {product.variation_label && (
                                                        <div className="small text-muted">{product.variation_label}</div>
                                                    )}
                                                </td>
                                                <td className="d-flex align-items-center justify-content-center">
                                                    <input
                                                        type="number"
                                                        min="1"
                                                        className="form-control w-50"
                                                        value={product.purchase_price}
                                                        onChange={(e) => handlePriceChange(lineKey(product), e.target.value)}
                                                    />
                                                </td>
                                                <td>{product.stock}</td>
                                                <td className="d-flex align-items-center justify-content-center">
                                                    <input
                                                        type="number"
                                                        min="1"
                                                        className="form-control w-50"
                                                        value={product.qty}
                                                        onChange={(e) => handleQtyChange(lineKey(product), e.target.value)}
                                                    />
                                                </td>
                                                <td>{parseFloat(product.subTotal || 0).toFixed(2)}</td>
                                                <td>
                                                    <button className="btn btn-danger btn-sm" onClick={() => handleDelete(lineKey(product))}>
                                                        Delete
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div className="row">
                            <div className="col-6"></div>
                            <div className="col-6">
                                <div className="table-responsive">
                                    <table className="table table-sm">
                                        <tbody>
                                            <tr>
                                                <th>Subtotal:</th>
                                                <td className="text-right">
                                                    {parseFloat(totals.subTotal || 0).toFixed(2)}

                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Tax:</th>
                                                <td className="text-right">
                                                    {totals.tax.toFixed(2)}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Discount:</th>
                                                <td className="text-right">
                                                    {totals.discount.toFixed(2)}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Shipping:</th>
                                                <td className="text-right">
                                                    {totals.shipping.toFixed(2)}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Grand Total:</th>
                                                <td className="text-right">
                                                    {totals.grandTotal.toFixed(
                                                        2
                                                    )}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div className="card">
                    <div className="card-body">
                        <div className="row">
                            <div className="mb-3 col-md-4">
                                <label htmlFor="tax" className="form-label">
                                    Tax
                                </label>
                                <input
                                    type="number"
                                    className="form-control"
                                    value={tax}
                                    min="0"
                                    onChange={(e) =>
                                        setTax(parseFloat(e.target.value) || 0)
                                    }
                                    placeholder="Enter tax"
                                    name="tax"
                                    required
                                />
                            </div>
                            <div className="mb-3 col-md-4">
                                <label
                                    htmlFor="discount"
                                    className="form-label"
                                >
                                    Discount
                                </label>
                                <input
                                    type="number"
                                    min="0"
                                    className="form-control"
                                    value={discount}
                                    onChange={(e) =>
                                        setDiscount(
                                            parseFloat(e.target.value) || 0
                                        )
                                    }
                                    placeholder="Enter discount"
                                    name="discount"
                                    required
                                />
                            </div>
                            <div className="mb-3 col-md-4">
                                <label
                                    htmlFor="shipping"
                                    className="form-label"
                                >
                                    Shipping Charge
                                </label>
                                <input
                                    type="number"
                                    min="0"
                                    className="form-control"
                                    value={shipping}
                                    onChange={(e) =>
                                        setShipping(
                                            parseFloat(e.target.value) || 0
                                        )
                                    }
                                    placeholder="Enter shipping"
                                    name="shipping"
                                    required
                                />
                            </div>
                        </div>
                    </div>
                </div>
                <button
                    type="submit"
                    className="btn action-button"
                    onClick={handleSubmit}
                >
                    Create
                </button>
            </div>

            <Toaster position="top-right" reverseOrder={false} />
        </>
    );
}
