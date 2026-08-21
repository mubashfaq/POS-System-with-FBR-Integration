import React from 'react'
import Pos from "./components/Pos";
import PosDigital from "./components/PosDigital";
import PosDigitalNew from "./components/PosDigitalNew";
import Purchase from './components/Purchase/Purchase';
import { createRoot } from 'react-dom/client';


const getDraftButtonSetting = (element) => {
    return element?.dataset.draftButton === "1";
};

// Check for the 'cart' element and render the 'cart' component using createRoot
const cartElement = document.getElementById("cart");

if (cartElement) {
    const businessType = cartElement.dataset.businessType;
    const draftButtonEnabled = getDraftButtonSetting(cartElement);

    const cartRoot = createRoot(cartElement);

    cartRoot.render(
        <Pos businessType={businessType} draftButtonEnabled={draftButtonEnabled} />
    );
}

const cartDigitalInvoiceElement = document.getElementById("cartDigitalInvoice");

if (cartDigitalInvoiceElement) {
    const draftButtonEnabled = getDraftButtonSetting(cartDigitalInvoiceElement);
    const cartRoot = createRoot(cartDigitalInvoiceElement);

    cartRoot.render(
        <PosDigital draftButtonEnabled={draftButtonEnabled} />
    );
}

const cartDigitalInvoiceNewElement = document.getElementById("cartDigitalInvoiceNew");

if (cartDigitalInvoiceNewElement) {
    const draftButtonEnabled = getDraftButtonSetting(cartDigitalInvoiceNewElement);

    const cartRoot = createRoot(cartDigitalInvoiceNewElement);

    cartRoot.render(
        <PosDigitalNew draftButtonEnabled={draftButtonEnabled} />
    );
}

// Check for the 'purchase' element and render the 'Purchase' component using createRoot
if (document.getElementById("purchase")) {
    const purchaseRoot = createRoot(
        document.getElementById("purchase")
    );
    purchaseRoot.render(<Purchase />);
}

