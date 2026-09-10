import Swal from "sweetalert2";

export function ConfirmBox({
    title,
    confirmText = "Yes",
    denyText = "No",
    icon,
}) {
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
    }).then((result) => result.isConfirmed);
}