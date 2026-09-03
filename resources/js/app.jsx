import React from "react";
import Pos from "./components/Pos";
import PosDigital from "./components/PosDigital";
import PosDigitalNew from "./components/PosDigitalNew";
import Purchase from "./components/Purchase/Purchase";
import { createRoot } from "react-dom/client";


const getDraftButtonSetting = (element) => {
    return element?.dataset.draftButton === "1";
};

const getCustomInvoiceNumberSetting = (element) => {
    return element?.dataset.customInvoiceNumber === "1";
};

const getPoNumberSetting = (element) => {
    return element?.dataset.poNumber === "1";
};


// POS
const cartElement = document.getElementById("cart");

if (cartElement) {
    const businessType = cartElement.dataset.businessType;

    const draftButtonEnabled =
        getDraftButtonSetting(cartElement);

    // const customInvoiceNumberEnabled =
    //     getCustomInvoiceNumberSetting(cartElement);

    // const poNumberEnabled =
    //     getPoNumberSetting(cartElement);

    const cartRoot = createRoot(cartElement);

    cartRoot.render(
        <Pos
            businessType={businessType}
            draftButtonEnabled={draftButtonEnabled}
            // customInvoiceNumberEnabled={customInvoiceNumberEnabled}
            // poNumberEnabled={poNumberEnabled}
        />
    );
}


// POS Digital
const cartDigitalInvoiceElement =
    document.getElementById("cartDigitalInvoice");

if (cartDigitalInvoiceElement) {

    const draftButtonEnabled =
        getDraftButtonSetting(cartDigitalInvoiceElement);

    const customInvoiceNumberEnabled =
        getCustomInvoiceNumberSetting(cartDigitalInvoiceElement);

    const poNumberEnabled =
        getPoNumberSetting(cartDigitalInvoiceElement);

    const cartRoot = createRoot(cartDigitalInvoiceElement);

    cartRoot.render(
        <PosDigital
            draftButtonEnabled={draftButtonEnabled}
            customInvoiceNumberEnabled={customInvoiceNumberEnabled}
            poNumberEnabled={poNumberEnabled}
        />
    );
}


// POS Digital New
const cartDigitalInvoiceNewElement =
    document.getElementById("cartDigitalInvoiceNew");

if (cartDigitalInvoiceNewElement) {

    const draftButtonEnabled =
        getDraftButtonSetting(cartDigitalInvoiceNewElement);

    const customInvoiceNumberEnabled =
        getCustomInvoiceNumberSetting(cartDigitalInvoiceNewElement);

    const poNumberEnabled =
        getPoNumberSetting(cartDigitalInvoiceNewElement);

    const cartRoot = createRoot(cartDigitalInvoiceNewElement);

    cartRoot.render(
        <PosDigitalNew
            draftButtonEnabled={draftButtonEnabled}
            customInvoiceNumberEnabled={customInvoiceNumberEnabled}
            poNumberEnabled={poNumberEnabled}
        />
    );
}


// Purchase
if (document.getElementById("purchase")) {

    const purchaseRoot = createRoot(
        document.getElementById("purchase")
    );

    purchaseRoot.render(<Purchase />);
}