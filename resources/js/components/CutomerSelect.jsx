import React, { useState, useEffect } from "react";
import CreatableSelect from "react-select/creatable";
import axios from "axios";
import CustomerCreateModal from "./CustomerCreateModal";
import toast from "react-hot-toast";

const CustomerSelect = ({
    setCustomerId,
    setIsRegistered,
    emptyCart,
    small = false,
}) => {
    const [customers, setCustomers] = useState([]);
    const [selectedCustomer, setSelectedCustomer] = useState({});

    const [showModal, setShowModal] = useState(false);
    const [pendingName, setPendingName] = useState("");

    useEffect(() => {
        axios.get("/admin/get/customers").then((response) => {
            const customerOptions = response?.data?.map((customer) => ({
                value: customer.id,
                label: customer.name,
            }));

            setCustomers(customerOptions);
        });
    }, []);

    const handleCreateCustomer = (inputValue) => {
        setPendingName(inputValue);
        setShowModal(true);
    };

    const handleModalCreated = async (newCustomer) => {
        const newOption = {
            value: newCustomer.id,
            label: newCustomer.name,
        };

        setCustomers((prev) => [newOption, ...prev]);

        await handleChange(newOption);
    };

    const handleChange = async (newValue) => {
        if (!newValue || !newValue.value) return;

        setSelectedCustomer(newValue);
        setCustomerId(newValue.value);

        await emptyCart();

        try {
            const res = await axios.post(
                `/admin/select-customer/${newValue.value}`
            );

            if (!res.data.customer) {
                throw new Error("Customer not found");
            }

            setIsRegistered(res.data.customer.is_registered);

        } catch (err) {
            console.error(err);
            toast.error("Failed to change customer");
        }
    };

    const selectStyles = {
        control: (provided) => ({
            ...provided,
            minHeight: small ? "31px" : "38px",
            height: small ? "31px" : "38px",
        }),

        valueContainer: (provided) => ({
            ...provided,
            height: small ? "31px" : "38px",
            padding: "0 8px",
        }),

        indicatorsContainer: (provided) => ({
            ...provided,
            height: small ? "31px" : "38px",
        }),

        input: (provided) => ({
            ...provided,
            margin: 0,
            padding: 0,
        }),
    };

    return (
        <>
            <CreatableSelect
                isClearable
                options={customers}
                onChange={handleChange}
                onCreateOption={handleCreateCustomer}
                value={selectedCustomer}
                placeholder="Select or create customer"
                styles={selectStyles}
            />

            <CustomerCreateModal
                show={showModal}
                onClose={() => {
                    setShowModal(false);
                    setPendingName("");
                }}
                initialName={pendingName}
                onCreated={handleModalCreated}
            />
        </>
    );
};

export default CustomerSelect;