// import './bootstrap';
import React from 'react'
import Pos from "./components/Pos";
import PosDigital from "./components/PosDigital";
import PosDigitalNew from "./components/PosDigitalNew";
import Purchase from './components/Purchase/Purchase';
import { createRoot } from 'react-dom/client';
// export default function app() {
//   return (
//     <Pos />
//   )
// }

// Check for the 'cart' element and render the 'cart' component using createRoot
if (document.getElementById("cart")) {
    const cartRoot = createRoot(document.getElementById("cart"));
    cartRoot.render(<Pos />);
}

if (document.getElementById("cartDigitalInvoice")) {
    const cartRoot = createRoot(document.getElementById("cartDigitalInvoice"));
    cartRoot.render(<PosDigital />);
}

if (document.getElementById("cartDigitalInvoiceNew")) {
    const cartRoot = createRoot(document.getElementById("cartDigitalInvoiceNew"));
    cartRoot.render(<PosDigitalNew />);
}

// Check for the 'purchase' element and render the 'Purchase' component using createRoot
if (document.getElementById("purchase")) {
    const purchaseRoot = createRoot(
        document.getElementById("purchase")
    );
    purchaseRoot.render(<Purchase />);
}

