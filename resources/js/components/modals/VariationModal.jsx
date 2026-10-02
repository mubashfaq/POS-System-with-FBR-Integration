import React from "react";

const VariationModal = ({
    show,
    product,
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
                className="variation-modal"
                onClick={(e) => e.stopPropagation()}
            >
                {/* Header */}
                <div className="variation-modal-header">
                    <div>
                        <h5 className="variation-modal-title">
                            Select Option
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
                <div className="variation-modal-body">
                    <div className="variation-list">
                        {(product?.variations || []).map((v) => {
                            const outOfStock = Number(v.stock) <= 0;

                            return (
                                <button
                                    key={v.id}
                                    type="button"
                                    className={`variation-item ${outOfStock &&
                                            quantityValidation === "disabled"
                                            ? "variation-disabled"
                                            : ""
                                        }`}
                                    disabled={
                                        outOfStock &&
                                        quantityValidation === "disabled"
                                    }
                                    onClick={() => onSelect(v)}
                                >
                                    <div className="variation-info">
                                        <span className="variation-label">
                                            {v.label}
                                        </span>

                                        <span className="variation-stock">
                                            Stock: {v.stock}
                                        </span>
                                    </div>

                                    <div className="variation-right">
                                        <span className="variation-price">
                                            {v.price}
                                        </span>

                                        <i className="fas fa-chevron-right"></i>
                                    </div>
                                </button>
                            );
                        })}
                    </div>
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

export default VariationModal;