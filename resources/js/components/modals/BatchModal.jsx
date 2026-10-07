import React from "react";

const BatchModal = ({
    show,
    product,
    batches,
    quantityValidation,
    onClose,
    onSelect,
}) => {
    if (!show) return null;

    return (
        <div
            className="variation-modal-overlay"
            onClick={onClose}
        >
            <div
                className="batch-modal"
                onClick={(e) => e.stopPropagation()}
            >
                {/* Header */}
                <div className="variation-modal-header">
                    <div>
                        <h5 className="variation-modal-title">
                            Select Batch
                        </h5>

                        <p className="variation-modal-product">
                            {product?.name}
                        </p>
                    </div>

                    <button
                        type="button"
                        className="variation-modal-close"
                        onClick={onClose}
                    >
                        <i className="fas fa-times"></i>
                    </button>
                </div>

                {/* Body */}
                <div className="batch-modal-body">
                    <div className="table-responsive">
                        <table className="batch-table">
                            <thead>
                                <tr>
                                    <th>Purchase Date</th>
                                    <th>Available Qty</th>
                                    <th>Original Qty</th>
                                    <th>Purchase Price</th>
                                    <th>Action</th>
                                </tr>
                            </thead>

                            <tbody>
                                {(batches || []).map((batch) => (
                                    <tr key={batch.id}>
                                        <td>{batch.purchase_date}</td>

                                        <td>
                                            <span className="batch-available">
                                                {batch.remaining_qty}
                                            </span>
                                        </td>

                                        <td>{batch.quantity}</td>

                                        <td>
                                            <span className="batch-price">
                                                {batch.purchase_price}
                                            </span>
                                        </td>

                                        <td>
                                            <button
                                                type="button"
                                                className="batch-select-btn"
                                                onClick={() =>
                                                    onSelect(batch)
                                                }
                                            >
                                                <i className="fas fa-check"></i>
                                                Select
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* Info */}
                    {quantityValidation === "enabled" && (
                        <div className="batch-info">
                            <i className="fas fa-info-circle"></i>

                            <span>
                                Batches with less than 1 quantity available
                                can also be selected.
                            </span>
                        </div>
                    )}
                </div>

                {/* Footer */}
                <div className="variation-modal-footer">
                    <button
                        type="button"
                        className="variation-close-btn"
                        onClick={onClose}
                    >
                        <i className="fas fa-times"></i>
                        Close
                    </button>
                </div>
            </div>
        </div>
    );
};

export default BatchModal;
