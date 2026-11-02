
import React, { useState, useEffect } from "react";
import axios from "axios";
import toast from "react-hot-toast";

const CustomerCreateModal = ({
    show,
    onClose,
    onCreated,
    initialName,
}) => {
    const [formData, setFormData] = useState({
        name: "",
        phone: "",
        ntn_cnic: "",
        province: "",
        address: "",
        is_registered: "Unregistered",
        sale_tax_number: "",
        customer_tax_type: "",
    });

    const [loading, setLoading] = useState(false);
    const [checkingCnic, setCheckingCnic] = useState(false);

    useEffect(() => {
        if (show) {
            setFormData({
                name: initialName || "",
                phone: "",
                ntn_cnic: "",
                province: "",
                address: "",
                is_registered: "Unregistered",
                sale_tax_number: "",
                customer_tax_type: "",
            });
        }
    }, [show, initialName]);

    useEffect(() => {
        if (!show || !formData.ntn_cnic.trim()) {
            setCheckingCnic(false);
            return;
        }

        let cancelled = false;

        const delay = setTimeout(async () => {
            setCheckingCnic(true);

            try {
                const res = await axios.post(
                    `/admin/customers/is-registered/${encodeURIComponent(formData.ntn_cnic.trim())}`
                );

                if (!cancelled) {
                    setFormData((prev) => ({
                        ...prev, is_registered: res.data.registered ? "Registered" : "Unregistered",
                    }));
                }
            } catch (error) {
                console.error("CNIC verification failed:", error);
            } finally {
                if (!cancelled) {
                    setCheckingCnic(false);
                }
            }
        }, 1000);

        return () => {
            cancelled = true;
            clearTimeout(delay);
        };
    }, [formData.ntn_cnic, show]);

    const handleChange = (e) => {
        const { name, value } = e.target;

        setFormData((prev) => ({
            ...prev, [name]: value,
        }));
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);

        try {
            const res = await axios.post("/admin/create/customers", formData, {
                headers: {
                    Accept: "application/json",
                },
            });

            toast.success("Customer created successfully!");

            if (onCreated) {
                onCreated(res.data);
            }

            onClose();
        } catch (error) {
            console.error(error);

            const errors = error.response?.data?.errors;
            const firstError = errors ? Object.values(errors).flat()[0] : null;

            toast.error(firstError || error.response?.data?.message || "Failed to create customer.");
        } finally {
            setLoading(false);
        }
    };

    if (!show) return null;

    return (
        <>
            <style>{`
                .pos-customer-overlay {
                    position: fixed;
                    inset: 0;
                    z-index: 1050;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                    background: rgba(15, 23, 42, 0.62);
                    backdrop-filter: blur(4px);
                    overflow-y: auto;
                }

                .pos-customer-modal {
                    width: 100%;
                    max-width: 760px;
                    margin: auto;
                    background: #fff;
                    border: 1px solid #e9edf2;
                    border-radius: 14px;
                    overflow: hidden;
                    box-shadow: 0 24px 70px rgba(15, 23, 42, 0.22);
                    animation: posCustomerEnter 0.2s ease-out;
                }

                @keyframes posCustomerEnter {
                    from {
                        opacity: 0;
                        transform: translateY(10px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }

                .pos-customer-header {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 16px;
                    padding: 22px 26px;
                    background: #fff;
                    border-bottom: 1px solid #edf0f4;
                }

                .pos-customer-heading {
                    display: flex;
                    align-items: center;
                    gap: 14px;
                    min-width: 0;
                }

                .pos-customer-heading-icon {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    flex-shrink: 0;
                    width: 46px;
                    height: 46px;
                    color: #fd6802;
                    background: #fff0e6;
                    border-radius: 12px;
                }

                .pos-customer-heading h5 {
                    margin: 0 0 5px;
                    color: #202938;
                    font-size: 19px;
                    font-weight: 700;
                    letter-spacing: -0.35px;
                }

                .pos-customer-heading p {
                    margin: 0;
                    color: #8490a0;
                    font-size: 12px;
                }

                .pos-customer-close {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    flex-shrink: 0;
                    width: 34px;
                    height: 34px;
                    padding: 0;
                    border: 1px solid #e9edf2;
                    border-radius: 8px;
                    background: #fff;
                    color: #697586;
                    font-size: 24px;
                    line-height: 1;
                    transition: all 0.2s ease;
                }

                .pos-customer-close:hover {
                    color: #fd6802;
                    background: #fff0e6;
                    border-color: #ffd0b0;
                }

                .pos-customer-body {
                    padding: 24px 26px;
                    max-height: calc(100vh - 200px);
                    overflow-y: auto;
                }

                .pos-customer-section {
                    margin-bottom: 23px;
                }

                .pos-customer-section:last-child {
                    margin-bottom: 0;
                }

                .pos-customer-section-title {
                    display: flex;
                    align-items: center;
                    gap: 9px;
                    margin-bottom: 16px;
                    color: #374151;
                    font-size: 12px;
                    font-weight: 700;
                    text-transform: uppercase;
                    letter-spacing: 0.7px;
                }

                .pos-customer-section-title::before {
                    content: "";
                    width: 3px;
                    height: 16px;
                    border-radius: 4px;
                    background: #fd6802;
                }

                .pos-customer-field {
                    margin-bottom: 17px;
                }

                .pos-customer-field label {
                    display: block;
                    margin-bottom: 7px;
                    color: #424b5a;
                    font-size: 12px;
                    font-weight: 600;
                }

                .pos-customer-required {
                    color: #e5484d;
                    margin-left: 2px;
                }

                .pos-customer-field .form-control {
                    width: 100%;
                    min-height: 42px;
                    padding: 10px 12px;
                    border: 1px solid #dfe4eb;
                    border-radius: 7px;
                    background-color: #fff;
                    color: #273142;
                    font-size: 13px;
                    box-shadow: none;
                    transition: border-color 0.2s, box-shadow 0.2s;
                }

                .pos-customer-field .form-control::placeholder {
                    color: #a4acb8;
                }

                .pos-customer-field .form-control:focus {
                    border-color: #fd6802;
                    box-shadow: 0 0 0 3px rgba(253, 104, 2, 0.11);
                    outline: none;
                }

                .pos-customer-field select.form-control {
                    cursor: pointer;
                }

                .pos-customer-field .form-hint {
                    display: block;
                    margin-top: 6px;
                    color: #8993a2;
                    font-size: 11px;
                }

                .pos-customer-status {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    min-height: 42px;
                    padding: 9px 12px;
                    border: 1px solid #e5e9ef;
                    border-radius: 7px;
                    background: #f8fafc;
                }

                .pos-customer-status-dot {
                    width: 8px;
                    height: 8px;
                    flex-shrink: 0;
                    border-radius: 50%;
                    background: #8993a2;
                }

                .pos-customer-status.is-registered .pos-customer-status-dot {
                    background: #16a36a;
                }

                .pos-customer-status.is-unregistered .pos-customer-status-dot {
                    background: #e59b18;
                }

                .pos-customer-status-text {
                    color: #4b5563;
                    font-size: 12px;
                    font-weight: 600;
                }

                .pos-customer-status small {
                    margin-left: auto;
                    color: #8993a2;
                    font-size: 10px;
                }

                .pos-customer-footer {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 12px;
                    padding: 17px 26px;
                    background: #fafbfc;
                    border-top: 1px solid #edf0f4;
                }

                .pos-customer-footer-note {
                    color: #8993a2;
                    font-size: 11px;
                }

                .pos-customer-actions {
                    display: flex;
                    justify-content: flex-end;
                    gap: 10px;
                }

                .pos-customer-btn {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    gap: 8px;
                    min-height: 39px;
                    padding: 9px 17px;
                    border-radius: 7px;
                    font-size: 12px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.2s ease;
                }

                .pos-customer-btn-cancel {
                    border: 1px solid #dfe4eb;
                    background: #fff;
                    color: #4b5563;
                }

                .pos-customer-btn-cancel:hover {
                    background: #f1f3f6;
                }

                .pos-customer-btn-submit {
                    border: 1px solid #fd6802;
                    background: #fd6802;
                    color: #fff;
                    box-shadow: 0 3px 8px rgba(253, 104, 2, 0.16);
                }

                .pos-customer-btn-submit:hover:not(:disabled) {
                    border-color: #e95d00;
                    background: #e95d00;
                    box-shadow: 0 4px 12px rgba(253, 104, 2, 0.25);
                }

                .pos-customer-btn:disabled {
                    opacity: 0.65;
                    cursor: not-allowed;
                }

                .pos-customer-spinner {
                    display: inline-block;
                    width: 13px;
                    height: 13px;
                    border: 2px solid currentColor;
                    border-right-color: transparent;
                    border-radius: 50%;
                    animation: posCustomerSpin 0.65s linear infinite;
                }

                @keyframes posCustomerSpin {
                    to { transform: rotate(360deg); }
                }

                @media (max-width: 576px) {
                    .pos-customer-overlay {
                        align-items: flex-start;
                        padding: 10px;
                    }

                    .pos-customer-modal {
                        margin: auto 0;
                        border-radius: 11px;
                    }

                    .pos-customer-header {
                        padding: 17px;
                    }

                    .pos-customer-heading-icon {
                        width: 40px;
                        height: 40px;
                    }

                    .pos-customer-heading h5 {
                        font-size: 16px;
                    }

                    .pos-customer-body {
                        padding: 18px 17px;
                        max-height: none;
                    }

                    .pos-customer-footer {
                        align-items: flex-start;
                        flex-direction: column;
                        padding: 15px 17px;
                    }

                    .pos-customer-actions {
                        width: 100%;
                    }

                    .pos-customer-actions .pos-customer-btn {
                        flex: 1;
                        padding-left: 10px;
                        padding-right: 10px;
                    }
                }
            `}</style>

            <div
                className="pos-customer-overlay"
                role="presentation"
                onMouseDown={(e) => {
                    if (e.target === e.currentTarget && !loading) {
                        onClose();
                    }
                }}
            >
                <div
                    className="pos-customer-modal"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="pos-customer-title"
                >
                    <div className="pos-customer-header">
                        <div className="pos-customer-heading">
                            <div className="pos-customer-heading-icon">
                                <svg
                                    width="23"
                                    height="23"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    strokeWidth="1.8"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    aria-hidden="true"
                                >
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                                    <circle cx="10" cy="7" r="4" />
                                    <path d="M20 8v6M17 11h6" />
                                </svg>
                            </div>

                            <div>
                                <h5 id="pos-customer-title">
                                    Create New Customer
                                </h5>
                                <p>
                                    Add customer details and tax information
                                </p>
                            </div>
                        </div>

                        <button
                            type="button"
                            className="pos-customer-close"
                            onClick={onClose}
                            disabled={loading}
                            aria-label="Close modal"
                        >
                            &times;
                        </button>
                    </div>

                    <form onSubmit={handleSubmit}>
                        <div className="pos-customer-body">
                            <div className="pos-customer-section">
                                <div className="pos-customer-section-title">
                                    Basic Information
                                </div>

                                <div className="row">
                                    <div className="col-md-6">
                                        <div className="pos-customer-field">
                                            <label htmlFor="customer-name">
                                                Customer Name
                                                <span className="pos-customer-required">*</span>
                                            </label>
                                            <input
                                                id="customer-name"
                                                type="text"
                                                className="form-control"
                                                name="name"
                                                placeholder="Enter customer name"
                                                value={formData.name}
                                                onChange={handleChange}
                                                required
                                            />
                                        </div>
                                    </div>

                                    <div className="col-md-6">
                                        <div className="pos-customer-field">
                                            <label htmlFor="customer-phone">
                                                Phone Number
                                                <span className="pos-customer-required">*</span>
                                            </label>
                                            <input
                                                id="customer-phone"
                                                type="text"
                                                className="form-control"
                                                name="phone"
                                                placeholder="e.g. 03XXXXXXXXX"
                                                value={formData.phone}
                                                onChange={handleChange}
                                                required
                                            />
                                        </div>
                                    </div>

                                    <div className="col-md-6">
                                        <div className="pos-customer-field">
                                            <label htmlFor="customer-ntn">
                                                NTN / CNIC
                                                <span className="pos-customer-required">*</span>
                                            </label>
                                            <input
                                                id="customer-ntn"
                                                type="text"
                                                className="form-control"
                                                name="ntn_cnic"
                                                placeholder="Enter NTN or CNIC"
                                                value={formData.ntn_cnic}
                                                onChange={handleChange}
                                                required
                                            />
                                            <span className="form-hint">
                                                {checkingCnic
                                                    ? "Checking registration status..."
                                                    : "Registration status is checked automatically."}
                                            </span>
                                        </div>
                                    </div>

                                    <div className="col-md-6">
                                        <div className="pos-customer-field">
                                            <label htmlFor="customer-province">
                                                Province
                                                <span className="pos-customer-required">*</span>
                                            </label>
                                            <input
                                                id="customer-province"
                                                type="text"
                                                className="form-control"
                                                name="province"
                                                placeholder="Enter province"
                                                value={formData.province}
                                                onChange={handleChange}
                                                required
                                            />
                                        </div>
                                    </div>

                                    <div className="col-12">
                                        <div className="pos-customer-field">
                                            <label htmlFor="customer-address">
                                                Customer Address
                                                <span className="pos-customer-required">*</span>
                                            </label>
                                            <input
                                                id="customer-address"
                                                type="text"
                                                className="form-control"
                                                name="address"
                                                placeholder="Enter complete address"
                                                value={formData.address}
                                                onChange={handleChange}
                                                required
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div className="pos-customer-section">
                                <div className="pos-customer-section-title">
                                    Tax & Registration Details
                                </div>

                                <div className="row">
                                    <div className="col-md-6">
                                        <div className="pos-customer-field">
                                            <label htmlFor="customer-registration">
                                                Registration Status
                                                <span className="pos-customer-required">*</span>
                                            </label>

                                            <div className={`pos-customer-status ${formData.is_registered === "Registered"
                                                ? "is-registered" : "is-unregistered"}`}>

                                                <span className="pos-customer-status-dot" />

                                                <select id="customer-registration" className="form-control" name="is_registered"
                                                    value={formData.is_registered} onChange={handleChange} required
                                                    style={{
                                                        minHeight: "22px", padding: "0", border: "none", backgroundColor: "transparent",
                                                    }}>
                                                    <option value="Registered">Registered</option>
                                                    <option value="Unregistered">Unregistered (4% further tax)</option>
                                                    <option value="Endconsumer">End Consumer</option>
                                                </select>

                                                <small>
                                                    {formData.is_registered === "Registered" ? "Registered" : "Customer"}
                                                </small>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="col-md-6">
                                        <div className="pos-customer-field">
                                            <label htmlFor="customer-sales-tax"> Sales Tax Number</label>
                                            <input id="customer-sales-tax" type="text" className="form-control" name="sale_tax_number"
                                                placeholder="Enter sales tax number" value={formData.sale_tax_number}
                                                onChange={handleChange} />
                                        </div>
                                    </div>

                                    <div className="col-md-6">
                                        <div className="pos-customer-field">
                                            <label htmlFor="customer-tax-type">
                                                Customer Tax Type
                                            </label>
                                            <select id="customer-tax-type" className="form-control" name="customer_tax_type"
                                                value={formData.customer_tax_type} onChange={handleChange}>
                                                <option value="">Select tax type</option>
                                                <option value="retailer"> Retailer (0.5% Customer Tax)</option>
                                                <option value="distributor"> Distributor (1% Customer Tax)</option>
                                            </select>
                                            <span className="form-hint">
                                                Select the applicable customer tax category.
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="pos-customer-footer">
                            <span className="pos-customer-footer-note">
                                <span className="pos-customer-required">*</span>
                                {" "}Required fields
                            </span>

                            <div className="pos-customer-actions">
                                <button type="button" className="pos-customer-btn pos-customer-btn-cancel" onClick={onClose}
                                    disabled={loading}>
                                    Cancel
                                </button>

                                <button type="submit" className="pos-customer-btn pos-customer-btn-submit" disabled={loading}>
                                    {loading ? (
                                        <>
                                            <span className="pos-customer-spinner" /> Creating...
                                        </>
                                    ) : (
                                        <>
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                                                <path d="M12 5v14M5 12h14" />
                                            </svg>
                                            Create Customer
                                        </>
                                    )}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </>
    );
};

export default CustomerCreateModal;
