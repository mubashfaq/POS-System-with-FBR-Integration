import React, { useState, useEffect } from "react";
import CreatableSelect from "react-select/creatable";
import axios from "axios";

const CustomerSelect = ({ setCustomerId ,setIsRegistered, emptyCart }) => {
    const [customers, setCustomers] = useState([]);
    const [selectedCustomer, setSelectedCustomer] = useState({});

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
  useEffect(() => {
    setCustomerId(selectedCustomer?.value);
  }, [selectedCustomer]);

    const handleCreateCustomer = (inputValue) => {
        axios
            .post("/admin/create/customers", { name: inputValue })
            .then((response) => {
                const newCustomer = response.data;
                const newOption = {
                    value: newCustomer.id,
                    label: newCustomer.name,
                };
                setCustomers((prev) => [newOption,...prev]);
                setSelectedCustomer(newOption);
            })
            .catch((error) => {
                console.error("Error creating customer:", error);
            });
    };

const handleChange = async (newValue) => {
    if (!newValue || !newValue.value) return;

    // 1️⃣ Set selected customer immediately
    setSelectedCustomer(newValue);
    await emptyCart(); 

    try {
        // 2️⃣ Select customer first
        const res = await axios.post(`/admin/select-customer/${newValue.value}`);
        if (!res.data.customer) throw new Error("Customer not found");
        setIsRegistered(res.data.customer.is_registered);

    } catch (err) {
        console.error(err);
        toast.error("Failed to change customer");
    }
};


    return (
        <CreatableSelect
            isClearable
            options={customers}
            onChange={handleChange}
            onCreateOption={handleCreateCustomer} // Handle creating a new customer
            value={selectedCustomer}
            placeholder="Select or create customer"
        />
    );
};

export default CustomerSelect;
