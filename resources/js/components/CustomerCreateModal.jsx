import React, { useState, useEffect } from "react";
import axios from "axios";
import toast from "react-hot-toast";

export default function CustomerCreateModal({
    show,
    onClose,
    onCreated,
    initialName,
}) {
    const [formData, setFormData] = useState({
        name: "",
        phone: "",
        ntn_cnic: "",
        province: "",
        address: "",
        is_registered: "Unregistered",
        customer_tax_type: "",
    });

    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (show) {
            setFormData({
                name: initialName || "",
                phone: "",
                ntn_cnic: "",
                province: "",
                address: "",
                is_registered: "Unregistered",
                customer_tax_type: "",
            });
        }
    }, [show, initialName]);

    useEffect(() => {
        console.log("CNIC changed:", formData.ntn_cnic);
        if (!formData.ntn_cnic) return;

        const delay = setTimeout(() => {
            checkCnic();
        }, 1000); // 0.10 sec delay

        return () => clearTimeout(delay);
    }, [formData.ntn_cnic]);

    const checkCnic = async () => {
        try {
            const res = await axios.post(
                `/admin/customers/is-registered/${formData.ntn_cnic}`,
            );

            setFormData((prev) => ({
                ...prev,
                is_registered: res.data.registered
                    ? "Registered"
                    : "Unregistered",
            }));
        } catch (err) {
            console.log(err);
        }
    };

    const handleChange = (e) => {
        const { name, value } = e.target;
        setFormData((prev) => ({ ...prev, [name]: value }));
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
            const errMsg =
                error.response?.data?.message || "Failed to create customer.";
            toast.error(errMsg);
            if (error.response?.data?.errors) {
                console.log(error.response.data.errors);
            }
        } finally {
            setLoading(false);
        }
    };

    if (!show) return null;

    return (
        <div
            className="modal show d-block"
            tabIndex="-1"
            style={{
                backgroundColor: "rgba(0,0,0,0.5)",
                overflowY: "auto",
                zIndex: 1050,
            }}
        >
            <div className="modal-dialog modal-lg">
                <div className="modal-content">
                    <div className="modal-header bg-primary text-white">
                        <h5 className="modal-title">Create New Customer</h5>
                        <button
                            type="button"
                            className="close text-white"
                            onClick={onClose}
                        >
                            <span>&times;</span>
                        </button>
                    </div>
                    <div className="modal-body">
                        <form onSubmit={handleSubmit}>
                            <div className="row">
                                <div className="mb-3 col-md-6">
                                    <label className="form-label">
                                        Name{" "}
                                        <span className="text-danger">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        className="form-control"
                                        name="name"
                                        value={formData.name}
                                        onChange={handleChange}
                                        required
                                    />
                                </div>
                                <div className="mb-3 col-md-6">
                                    <label className="form-label">Phone</label>
                                    <input
                                        type="text"
                                        className="form-control"
                                        name="phone"
                                        value={formData.phone}
                                        onChange={handleChange}
                                    />
                                </div>
                                <div className="mb-3 col-md-6">
                                    <label className="form-label">
                                        NTN/CNIC
                                    </label>
                                    <input
                                        type="text"
                                        className="form-control"
                                        name="ntn_cnic"
                                        value={formData.ntn_cnic}
                                        onChange={handleChange}
                                    />
                                </div>
                                <div className="mb-3 col-md-6">
                                    <label className="form-label">
                                        Province
                                    </label>
                                    <input
                                        type="text"
                                        className="form-control"
                                        name="province"
                                        value={formData.province}
                                        onChange={handleChange}
                                    />
                                </div>
                                <div className="mb-3 col-md-12">
                                    <label className="form-label">
                                        Address
                                    </label>
                                    <input
                                        type="text"
                                        className="form-control"
                                        name="address"
                                        value={formData.address}
                                        onChange={handleChange}
                                    />
                                </div>
                                <div className="mb-3 col-md-6">
                                    <label className="form-label">
                                        Registration Status{" "}
                                        <span className="text-danger">*</span>
                                    </label>
                                    <select
                                        className="form-control"
                                        name="is_registered"
                                        value={formData.is_registered}
                                        onChange={handleChange}
                                        required
                                    >
                                        <option value="Registered">
                                            Registered
                                        </option>
                                        <option value="Unregistered">
                                            Unregistered
                                        </option>
                                        <option value="Endconsumer">
                                        End Consumer
                                        </option>
                                    </select>
                                </div>
                                <div className="mb-3 col-md-6">
                                    <label className="form-label">
                                        Customer Tax Type
                                    </label>
                                    <select
                                        className="form-control"
                                        name="customer_tax_type"
                                        value={formData.customer_tax_type}
                                        onChange={handleChange}
                                    >
                                        <option value="">
                                            Select Tax Type
                                        </option>
                                        <option value="retailer">
                                            Retailer
                                        </option>
                                        <option value="distributor">
                                            Distributor
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <div className="modal-footer px-0 pb-0 border-top mt-3 pt-3">
                                <button
                                    type="button"
                                    className="btn btn-secondary"
                                    onClick={onClose}
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    className="btn btn-primary"
                                    disabled={loading}
                                >
                                    {loading
                                        ? "Creating..."
                                        : "Create Customer"}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    );
}
