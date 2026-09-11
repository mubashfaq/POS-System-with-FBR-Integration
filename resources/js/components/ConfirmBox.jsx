import Swal from "sweetalert2";
import "../../css/app.css";


export function ConfirmBox({
    title,
    confirmText = "Yes",
    denyText = "No",
    icon,
    extraButton, // { text, className? } — optional third button
}) {
    if (!extraButton) {
        return Swal.fire({
            title,
            icon,
            showDenyButton: true,
            confirmButtonText: confirmText,
            denyButtonText: denyText,
            customClass: {
                actions: "my-actions",
                cancelButton: "order-1 right-gap",
                confirmButton: "order-2",
                denyButton: "order-3",
            },
        }).then((result) => (result.isConfirmed ? "confirm" : result.isDenied ? "deny" : null));
    }

    // Three-button mode: Yes | Extra | No
    return new Promise((resolve) => {
        let resolved = false;

        const finish = (value) => {
            if (resolved) return;
            resolved = true;
            Swal.close();
            resolve(value);
        };

        Swal.fire({
            title,
            icon,
            customClass: {
                title: "confirm-box-title",
            },
            html: `
                <div class="my-actions">
                    <button id="cb-confirm" class="cb-btn cb-confirm">
                        ${confirmText}
                    </button>

                    <button id="cb-extra" class="cb-btn cb-extra">
                        ${extraButton.text}
                    </button>

                    <button id="cb-deny" class="cb-btn cb-deny">
                        ${denyText}
                    </button>
                </div>
            `,
            showConfirmButton: false,
            showDenyButton: false,
            showCancelButton: false,
            allowOutsideClick: false,
            didOpen: () => {
                const popup = Swal.getPopup();
                popup.querySelector("#cb-confirm").addEventListener("click", () => finish("confirm"));
                popup.querySelector("#cb-extra").addEventListener("click", () => finish("extra"));
                popup.querySelector("#cb-deny").addEventListener("click", () => finish("deny"));
            },
            willClose: () => {
                // If closed by ESC / backdrop, resolve as null
                if (!resolved) {
                    resolved = true;
                    resolve(null);
                }
            },
        });
    });
}