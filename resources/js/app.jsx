// import './bootstrap';
import React from 'react'
import Pos from "./components/Pos";
import PosDigital from "./components/PosDigital";
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

// Check for the 'purchase' element and render the 'Purchase' component using createRoot
if (document.getElementById("purchase")) {
    const purchaseRoot = createRoot(
        document.getElementById("purchase")
    );
    purchaseRoot.render(<Purchase />);
}

