import React, { useState, useEffect } from "react";
import axios from "axios";
import toast from "react-hot-toast";

export default function ProductCreateDigitalModal({ show, onClose, onCreated }) {
    const [formData, setFormData] = useState({
        name: "",
        price: "",
        purchase_price: "",
        quantity: 0,
        status: 1,

        // POS
        PCTCode: "",
        pos_sale_tax: "",

        // Digital
        hsCode: "",
        hs_description: "",
        uom: "",
        salesTaxWithheldAtSource: 0,
        sale_type: "Goods at standard rate (default)",
        sale_type_id: "",
        rate_of_tax: "",
        rate_of_tax_id: "",
        sro_schedule: "",
        sro_schedule_id: "",
        sro_item: "",
        sro_item_id: "",
    });

    const [formOptions, setFormOptions] = useState(null);
    const [loading, setLoading] = useState(false);

    // Dynamic dependent options (only used when FBR integration is on)
    const [uomOptions, setUomOptions] = useState([]);
    const [taxRateOptions, setTaxRateOptions] = useState([]);
    const [sroScheduleOptions, setSroScheduleOptions] = useState([]);
    const [sroItemOptions, setSroItemOptions] = useState([]);

    const [saleData, setSaleData] = useState([]);
    const [sroData, setSroData] = useState([]);
    const [sroItemDataState, setSroItemDataState] = useState([]);

    const [imageFile, setImageFile] = useState(null);
    const [imagePreview, setImagePreview] = useState(null);

    // Same flag as blade's `$fbrIntegration` / `fbrIntegrationEnabled`
    const fbrIntegration = !!formOptions?.fbrIntegration;

    useEffect(() => {
        if (show && !formOptions) {
            fetchFormData();
        }
    }, [show]);

    useEffect(() => {
        if (formOptions && fbrIntegration) {
            // Only auto-cascade from the API when FBR integration is enabled
            handleSaleTypeChange({ target: { value: "Goods at standard rate (default)" } });
        }
    }, [formOptions]);

    const fetchFormData = async () => {
        try {
            const res = await axios.get("/admin/get/product-form-data");
            setFormOptions(res.data);

            // set defaults if needed
        } catch (error) {
            console.error(error);
            toast.error("Failed to load form options");
        }
    };

    const handleChange = (e) => {
        const { name, value } = e.target;
        setFormData(prev => ({ ...prev, [name]: value }));
    };

    // ===============================
    // Extraction of cascading fetches (FBR-integrated flow only)
    // ===============================
    const fetchSroItem = async (sroId) => {
        try {
            const res = await axios.get(`/admin/products/sro-Items-details/${sroId}`);
            const data = Array.isArray(res.data.sro_data) ? res.data.sro_data : (res.data.sro_data?.data || []);
            setSroItemDataState(data);

            const options = data.map(item => ({ desc: item.srO_ITEM_DESC, id: item.srO_ITEM_ID }));
            setSroItemOptions(options);
            // Auto fill first if exists
            if (options.length > 0) {
                setFormData(prev => ({ ...prev, sro_item: options[0].desc, sro_item_id: options[0].id }));
            }
        } catch (error) {
            console.error(error);
        }
    };

    const fetchSroSchedule = async (rateId) => {
        try {
            const res = await axios.get(`/admin/products/sro-schedule/${rateId}`);
            const data = res.data.sro_data || [];
            setSroData(data);

            const options = data.map(item => ({ desc: item.srO_DESC, id: item.srO_ID }));
            setSroScheduleOptions(options);
            if (options.length > 0) {
                setFormData(prev => ({ ...prev, sro_schedule: options[0].desc, sro_schedule_id: options[0].id }));
                await fetchSroItem(options[0].id);
            }
        } catch (error) {
            console.error(error);
        }
    };

    // HS Code change (FBR-integrated: dropdown -> API lookup of description/UOM)
    const handleHsCodeChange = async (e) => {
        const hsCode = e.target.value;
        setFormData(prev => ({ ...prev, hsCode, hs_description: "", uom: "" }));
        setUomOptions([]);

        if (!hsCode) return;

        try {
            const res = await axios.get(`/admin/products/hsCode-desc/${hsCode}`);
            setFormData(prev => ({ ...prev, hs_description: res.data.description }));
            if (res.data.uom) {
                const uomArr = Array.isArray(res.data.uom) ? res.data.uom : (res.data.uom?.data || []);
                setUomOptions(uomArr);
                if (uomArr.length > 0) {
                    setFormData(prev => ({ ...prev, uom: uomArr[0].description }));
                }
            }
        } catch (error) {
            console.error(error);
        }
    };

    // Sale Type Change (FBR-integrated: dropdown -> API cascade)
    const handleSaleTypeChange = async (e) => {
        const sale_type = e.target.value;
        setFormData(prev => ({
            ...prev,
            sale_type,
            rate_of_tax: "", rate_of_tax_id: "",
            sro_schedule: "", sro_schedule_id: "",
            sro_item: "", sro_item_id: ""
        }));

        setTaxRateOptions([]);
        setSroScheduleOptions([]);
        setSroItemOptions([]);

        if (!sale_type) return;

        try {
            const res = await axios.get(`/admin/products/sale-type/${sale_type}`);
            const data = res.data.sale_data || [];
            setSaleData(data);

            const options = data.map(item => ({ desc: item.ratE_DESC, id: item.ratE_ID }));
            setTaxRateOptions(options);
            if (options.length > 0) {
                setFormData(prev => ({ ...prev, rate_of_tax: options[0].desc, rate_of_tax_id: options[0].id }));
                await fetchSroSchedule(options[0].id);
            }
        } catch (error) {
            console.error(error);
        }
    };

    const handleTaxRateChange = async (e) => {
        const rateDesc = e.target.value;

        const selectedItem = saleData.find(item => item.ratE_DESC === rateDesc);

        setFormData(prev => ({
            ...prev,
            rate_of_tax: rateDesc,
            rate_of_tax_id: selectedItem ? selectedItem.ratE_ID : "",
            sro_schedule: "", sro_schedule_id: "",
            sro_item: "", sro_item_id: ""
        }));

        setSroScheduleOptions([]);
        setSroItemOptions([]);

        if (!selectedItem) return;

        await fetchSroSchedule(selectedItem.ratE_ID);
    };

    const handleSroScheduleChange = async (e) => {
        const sroDesc = e.target.value;
        const selectedItem = sroData.find(item => item.srO_DESC === sroDesc);

        setFormData(prev => ({
            ...prev,
            sro_schedule: sroDesc,
            sro_schedule_id: selectedItem ? selectedItem.srO_ID : "",
            sro_item: "", sro_item_id: ""
        }));

        setSroItemOptions([]);

        if (!selectedItem) return;

        await fetchSroItem(selectedItem.srO_ID);
    };

    const handleSroItemChange = (e) => {
        const itemDesc = e.target.value;
        const selectedItem = sroItemDataState.find(item => item.srO_ITEM_DESC === itemDesc);
        setFormData(prev => ({
            ...prev,
            sro_item: itemDesc,
            sro_item_id: selectedItem ? selectedItem.srO_ITEM_ID : ""
        }));
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        try {
            const formDataToSend = new FormData();
            Object.keys(formData).forEach(key => {
                if (formData[key] !== null) {
                    formDataToSend.append(key, formData[key]);
                }
            });

            if (imageFile) {
                formDataToSend.append("product_image", imageFile);
            }

            const res = await axios.post("/admin/create/ajax-product", formDataToSend);
            toast.success("Product created successfully!");
            if (onCreated) {
                onCreated(res.data.data.id);
            }
            onClose();
        } catch (error) {
            console.error(error);
            const errMsg = error.response?.data?.message || "Failed to create product.";
            toast.error(errMsg);
            if (error.response?.data?.errors) {
                console.log(error.response.data.errors);
            }
        } finally {
            setLoading(false);
        }
    };

    if (!show) return null;

    const bType = formOptions?.business_type || 'digital';

    return (
        <div className="modal show d-block" tabIndex="-1" style={{ backgroundColor: 'rgba(0,0,0,0.5)', overflowY: 'auto', zIndex: 2000 }}>
            <div className="modal-dialog modal-xl">
                <div className="modal-content">
                    <div className="modal-header bg-primary text-white">
                        <h5 className="modal-title">Create New Product</h5>
                        <button type="button" className="close text-white" onClick={onClose}>
                            <span>&times;</span>
                        </button>
                    </div>
                    <div className="modal-body">
                        {!formOptions ? (
                            <div className="text-center py-4">Loading form options...</div>
                        ) : (
                            <form onSubmit={handleSubmit}>
                                <div className="row">
                                    <div className="mb-3 col-md-6">
                                        <label className="form-label">Name <span className="text-danger">*</span></label>
                                        <input type="text" className="form-control" name="name" value={formData.name} onChange={handleChange} required />
                                    </div>
                                    <div className="mb-3 col-md-6">
                                        <label className="form-label">Price <span className="text-danger">*</span></label>
                                        <input type="number" step="0.01" min="0" className="form-control" name="price" value={formData.price} onChange={handleChange} required />
                                    </div>
                                    <div className="mb-3 col-md-6">
                                        <label className="form-label">Purchase Price <span className="text-danger">*</span></label>
                                        <input type="number" step="0.01" min="0" className="form-control" name="purchase_price" value={formData.purchase_price} onChange={handleChange} required />
                                    </div>
                                    <div className="mb-3 col-md-6">
                                        <label className="form-label">Stock Quantity</label>
                                        <input type="number" className="form-control" name="quantity" value={formData.quantity} onChange={handleChange} min="0" step="1"  />
                                    </div>

                                    {bType === 'pos' || bType === 'pral' ? (
                                        <>
                                            <div className="mb-3 col-md-6">
                                                <label className="form-label">PCT Code <span className="text-danger">*</span></label>
                                                <input type="text" className="form-control" name="PCTCode" value={formData.PCTCode} onChange={handleChange} required />
                                            </div>
                                            <div className="mb-3 col-md-6">
                                                <label className="form-label">POS Sale Tax <span className="text-danger">*</span></label>
                                                <input type="text" className="form-control" name="pos_sale_tax" value={formData.pos_sale_tax} onChange={handleChange} required />
                                            </div>
                                        </>
                                    ) : (
                                        <>
                                            {/* ===== HS CODE -> DESCRIPTION / UOM ===== */}
                                            {fbrIntegration ? (
                                                <>
                                                    <div className="mb-3 col-md-6">
                                                        <label className="form-label">HS Code <span className="text-danger">*</span></label>
                                                        <select className="form-control" name="hsCode" value={formData.hsCode} onChange={handleHsCodeChange} required>
                                                            <option value="">Select HS Code</option>
                                                            {formOptions.hsCodes?.map(hc => <option key={hc.id || hc.hs_code} value={hc.hs_code}>{hc.hs_code}</option>)}
                                                        </select>
                                                    </div>
                                                    <div className="mb-3 col-md-6">
                                                        <label className="form-label">Description <span className="text-danger">*</span></label>
                                                        <textarea rows="2" className="form-control" name="hs_description" value={formData.hs_description} readOnly required></textarea>
                                                    </div>
                                                    <div className="mb-3 col-md-6">
                                                        <label className="form-label">UOM <span className="text-danger">*</span></label>
                                                        <select className="form-control" name="uom" value={formData.uom} onChange={handleChange} required>
                                                            <option value="">Select Unit</option>
                                                            {uomOptions.map((u, i) => <option key={i} value={u.description}>{u.description}</option>)}
                                                            {uomOptions.length === 0 && formOptions.units?.map(u => <option key={u.id || u.description} value={u.description}>{u.description}</option>)}
                                                        </select>
                                                    </div>
                                                </>
                                            ) : (
                                                <>
                                                    <div className="mb-3 col-md-6">
                                                        <label className="form-label">HS Code <span className="text-danger">*</span></label>
                                                        <input type="text" className="form-control" name="hsCode" placeholder="Enter HS Code" value={formData.hsCode} onChange={handleChange} required />
                                                    </div>
                                                    <div className="mb-3 col-md-6">
                                                        <label className="form-label">Description <span className="text-danger">*</span></label>
                                                        <textarea rows="2" className="form-control" name="hs_description" placeholder="Enter Description" value={formData.hs_description} onChange={handleChange} required></textarea>
                                                    </div>
                                                    <div className="mb-3 col-md-6">
                                                        <label className="form-label">UOM <span className="text-danger">*</span></label>
                                                        <input type="text" className="form-control" name="uom" placeholder="Enter UOM" value={formData.uom} onChange={handleChange} required />
                                                    </div>
                                                </>
                                            )}

                                            <div className="mb-3 col-md-6">
                                                <label className="form-label">Sales Tax Withheld <span className="text-danger">*</span></label>
                                                <input type="number" step="0.01" className="form-control" name="salesTaxWithheldAtSource" value={formData.salesTaxWithheldAtSource} onChange={handleChange} required />
                                            </div>

                                            {/* ===== SALE TYPE -> RATE OF TAX -> SRO SCHEDULE -> SRO ITEM ===== */}
                                            {fbrIntegration ? (
                                                <>
                                                    <div className="mb-3 col-md-6">
                                                        <label className="form-label">Sale Type <span className="text-danger">*</span></label>
                                                        <select className="form-control" name="sale_type" value={formData.sale_type} onChange={handleSaleTypeChange} required>
                                                            <option value="">Select Sale Type</option>
                                                            {formOptions.saleTypes?.map(st => <option key={st.id || st.transactioN_DESC} value={st.transactioN_DESC}>{st.transactioN_DESC}</option>)}
                                                        </select>
                                                    </div>
                                                    <div className="mb-3 col-md-6">
                                                        <label className="form-label">Rate of Tax <span className="text-danger">*</span></label>
                                                        <select className="form-control" name="rate_of_tax" value={formData.rate_of_tax} onChange={handleTaxRateChange} required>
                                                            <option value="">Select Sale Tax</option>
                                                            {taxRateOptions.map((opt, i) => <option key={i} value={opt.desc}>{opt.desc}</option>)}
                                                        </select>
                                                    </div>
                                                    <div className="mb-3 col-md-6">
                                                        <label className="form-label">SRO Schedule</label>
                                                        <select className="form-control" name="sro_schedule" value={formData.sro_schedule} onChange={handleSroScheduleChange}>
                                                            <option value="">Select SRO Schedule</option>
                                                            {sroScheduleOptions.map((opt, i) => <option key={i} value={opt.desc}>{opt.desc}</option>)}
                                                        </select>
                                                    </div>
                                                    <div className="mb-3 col-md-6">
                                                        <label className="form-label">SRO Item Code</label>
                                                        <select className="form-control" name="sro_item" value={formData.sro_item} onChange={handleSroItemChange}>
                                                            <option value="">Select SRO Item</option>
                                                            {sroItemOptions.map((opt, i) => <option key={i} value={opt.desc}>{opt.desc}</option>)}
                                                        </select>
                                                    </div>
                                                </>
                                            ) : (
                                                <>
                                                    <div className="mb-3 col-md-6">
                                                        <label className="form-label">Sale Type <span className="text-danger">*</span></label>
                                                        <input type="text" className="form-control" name="sale_type" placeholder="Enter Sale Type" value={formData.sale_type} onChange={handleChange} required />
                                                    </div>
                                                    <div className="mb-3 col-md-6">
                                                        <label className="form-label">Rate of Tax <span className="text-danger">*</span></label>
                                                        <input type="text" className="form-control" name="rate_of_tax" placeholder="Enter Rate of Tax" value={formData.rate_of_tax} onChange={handleChange} required />
                                                    </div>
                                                    <div className="mb-3 col-md-6">
                                                        <label className="form-label">SRO Schedule</label>
                                                        <input type="text" className="form-control" name="sro_schedule" placeholder="Enter Sro Schedule" value={formData.sro_schedule} onChange={handleChange} />
                                                    </div>
                                                    <div className="mb-3 col-md-6">
                                                        <label className="form-label">SRO Item Code</label>
                                                        <input type="text" className="form-control" name="sro_item" placeholder="Enter Sro Item Code" value={formData.sro_item} onChange={handleChange} />
                                                    </div>
                                                </>
                                            )}
                                        </>
                                    )}

                                </div>
                                <div className="modal-footer px-0 pb-0 border-top mt-3 pt-3">
                                    <button type="button" className="btn btn-secondary" onClick={onClose}>Cancel</button>
                                    <button type="submit" className="btn btn-primary" disabled={loading}>
                                        {loading ? "Creating..." : "Create Product"}
                                    </button>
                                </div>
                            </form>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}