import Swal from "sweetalert2";
import "../../css/app.css";

export function ConfirmBox({
    title,
    subtitle = "Choose what you want to do with this order.",
    confirmText = "Yes",
    denyText = "No",
    icon,
    extraButton,
    fbrEnabled
}) {
    if (!extraButton) {
        return Swal.fire({
            title,
            text: subtitle,
            icon,
            showDenyButton: true,
            confirmButtonText: confirmText,
            denyButtonText: denyText,

            customClass: {
                popup: "checkout-popup",
                title: "checkout-title",
                htmlContainer: "checkout-subtitle",
                actions: "checkout-actions",
                confirmButton: "checkout-confirm",
                denyButton: "checkout-deny",
            },

            buttonsStyling: false,
        }).then((result) =>
            result.isConfirmed
                ? "confirm"
                : result.isDenied
                    ? "deny"
                    : null
        );
    }

    const extraButtonHtml = fbrEnabled
        ? `
            <button id="cb-extra" class="cb-btn cb-extra">
                <i class="fas fa-save"></i>
                <span>${extraButton.text}</span>
            </button>
        `
        : "";

    const confirmIcon = fbrEnabled
        ? `<i class="fas fa-paper-plane"></i>`
        : `<i class="fas fa-check"></i>`;

    return new Promise((resolve) => {
        let resolved = false;

        const finish = (value) => {
            if (resolved) return;

            resolved = true;
            Swal.close();
            resolve(value);
        };

        Swal.fire({
            html: `
                <div class="checkout-content">

                    <div class="checkout-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>

                    <div class="checkout-title">
                        ${title}
                    </div>

                    <div class="checkout-subtitle">
                        ${subtitle}
                    </div>

                    <div class="checkout-actions">

                        <button id="cb-confirm" class="cb-btn cb-confirm">
                            ${confirmIcon}
                            <span>${confirmText}</span>
                        </button>

                        ${extraButtonHtml}

                        <button id="cb-deny" class="cb-btn cb-deny">
                            <i class="fas fa-times"></i>
                            <span>${denyText}</span>
                        </button>

                    </div>

                </div>
            `,

            showConfirmButton: false,
            showDenyButton: false,
            showCancelButton: false,

            allowOutsideClick: false,

            customClass: {
                popup: "checkout-popup",
            },

            didOpen: () => {
                const popup = Swal.getPopup();

                popup
                    .querySelector("#cb-confirm")
                    .addEventListener("click", () =>
                        finish("confirm")
                    );

                if (fbrEnabled) {
                    popup
                        .querySelector("#cb-extra")
                        .addEventListener("click", () =>
                            finish("extra")
                        );
                }

                popup
                    .querySelector("#cb-deny")
                    .addEventListener("click", () =>
                        finish("deny")
                    );
            },

            willClose: () => {
                if (!resolved) {
                    resolved = true;
                    resolve(null);
                }
            },
        });
    });
}