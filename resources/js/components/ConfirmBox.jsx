import Swal from "sweetalert2";

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
            html: `
                <div class="my-actions" style="display:flex;gap:8px;justify-content:center;margin-top:16px;">
                    <button id="cb-confirm" class="swal2-confirm swal2-styled">${confirmText}</button>
                    <button id="cb-extra" class="swal2-styled" style="background-color:#6c757d;color:#fff;border:none;border-radius:6px;">${extraButton.text}</button>
                    <button id="cb-deny" class="swal2-deny swal2-styled">${denyText}</button>
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