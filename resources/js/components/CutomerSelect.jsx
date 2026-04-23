import React, { useState, useEffect } from "react";
import CreatableSelect from "react-select/creatable";
import axios from "axios";
import CustomerCreateModal from "./CustomerCreateModal";
import toast from "react-hot-toast"; 

const CustomerSelect = ({ setCustomerId ,setIsRegistered, emptyCart }) => {
    const [customers, setCustomers] = useState([]);
    const [selectedCustomer, setSelectedCustomer] = useState({});

     // ✅ Modal state
     const [showModal, setShowModal] = useState(false);
     const [pendingName, setPendingName] = useState("");

    // Fetch existing customers from the backend
    useEffect(() => {
      axios.get("/admin/get/customers").then((response) => {
            const customerOptions = response?.data?.map((customer) => ({
                value: customer.id,
                label: customer.name,
            }));
            setCustomers(customerOptions);
        });
    }, []);
  // customerId is now set directly in handleChange / handleModalCreated
  // (removed useEffect so it is never one render cycle late)

    // ✅ Instead of creating directly, open the modal with the typed name
    const handleCreateCustomer = (inputValue) => {
        setPendingName(inputValue);
        setShowModal(true);
    };

    // ✅ Called by modal after successful creation
    const handleModalCreated = async (newCustomer) => {
        const newOption = {
            value: newCustomer.id,
            label: newCustomer.name,
        };
        setCustomers((prev) => [newOption, ...prev]);
        // setShowModal(false);
        // setPendingName("");
        await handleChange(newOption); // apply same logic as selecting existing
    };

const handleChange = async (newValue) => {
    if (!newValue || !newValue.value) return;

    // Set both UI state and parent ID immediately (no deferred useEffect)
    setSelectedCustomer(newValue);
    setCustomerId(newValue.value); // ✅ immediate — parent knows customer before product click
    await emptyCart();

    try {
        const res = await axios.post(`/admin/select-customer/${newValue.value}`);
        if (!res.data.customer) throw new Error("Customer not found");
        setIsRegistered(res.data.customer.is_registered);
    } catch (err) {
        console.error(err);
        toast.error("Failed to change customer");
    }
};


    return (
        <>
        <CreatableSelect
            isClearable
            options={customers}
            onChange={handleChange}
            onCreateOption={handleCreateCustomer} // Handle creating a new customer
            value={selectedCustomer}
            placeholder="Select or create customer"
        />

        {/* ✅ Modal receives pre-filled name and reports back created customer */}
            <CustomerCreateModal
                show={showModal}
                onClose={() => { setShowModal(false); setPendingName(""); }}
                initialName={pendingName}
                onCreated={handleModalCreated}
            />
        </>
    );
};

export default CustomerSelect;
