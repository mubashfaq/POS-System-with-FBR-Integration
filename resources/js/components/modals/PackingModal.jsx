
import React from "react";

const PackingModal = ({
    isOpen,
    item,
    onClose,
    onSelectUnit,
    skipIfSameUnit = false,   // NEW: Cart passes true, POS leaves false
}) => {
    if (!isOpen || !item) return null;

    const levels = item.packing_levels || [];
    const currentUnitId = item.qty_unit_id;

    const baseUnitName =
        item.base_unit_name ||
        item.product?.base_unit_name ||
        item.product?.unit?.title ||
        "Piece";

    const handleSelect = async (unitId) => {
        if (skipIfSameUnit && Number(unitId) === Number(currentUnitId)) {
            onClose();
            return;
        }

        const success = await onSelectUnit(item.id, unitId);

        if (success) {
            onClose();
        }
    };

    return (
        <div
            className="variation-modal-overlay"
            onClick={onClose}
        >
            <div
                className="variation-modal packing-modal"
                onClick={(e) => e.stopPropagation()}
            >
                {/* Header */}
                <div className="variation-modal-header">
                    <div>
                        <h5 className="variation-modal-title">
                            Select Packaging Unit
                        </h5>

                        <p className="variation-modal-product">
                            {item.product?.name}
                            {item.variation_label
                                ? ` - ${item.variation_label}`
                                : ""}
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
                    <div className="packing-description">
                        <i className="fas fa-info-circle"></i>
                        Choose the unit you want to use for this sale.
                    </div>

                    {levels.length > 0 ? (
                        <div className="variation-list">
                            {levels.map((lvl) => {
                                const isSelected =
                                    Number(lvl.unit_id) ===
                                    Number(currentUnitId);

                                const factor =
                                    Number(lvl.base_qty) || 1;

                                const price =
                                    lvl.price ??
                                    lvl.suggested_price ??
                                    0;

                                return (
                                    <button
                                        key={lvl.unit_id}
                                        type="button"
                                        className={`variation-item packing-item ${isSelected
                                            ? "packing-item-selected"
                                            : ""
                                            }`}
                                        onClick={() =>
                                            handleSelect(lvl.unit_id)
                                        }
                                    >
                                        <div className="variation-info">
                                            <div className="d-flex align-items-center flex-wrap">
                                                <span className="variation-label">
                                                    {lvl.title}
                                                </span>

                                                {isSelected && (
                                                    <span className="packing-selected-badge">
                                                        <i className="fas fa-check-circle mr-1"></i>
                                                        Selected
                                                    </span>
                                                )}
                                            </div>

                                            <span className="variation-stock">
                                                <i className="fas fa-box-open mr-1"></i>
                                                1 {lvl.title} = {factor} {baseUnitName}
                                            </span>
                                        </div>

                                        <div className="variation-right">
                                            <div className="text-right">
                                                <span className="variation-price">
                                                    Rs.{" "}
                                                    {Number(price).toLocaleString(
                                                        undefined,
                                                        {
                                                            minimumFractionDigits: 2,
                                                            maximumFractionDigits: 2,
                                                        }
                                                    )}
                                                </span>

                                                <small className="packing-price-label">
                                                    Per {lvl.title}
                                                </small>
                                            </div>

                                            <i
                                                className={`fas ${isSelected
                                                    ? "fa-check-circle packing-check"
                                                    : "fa-chevron-right"
                                                    }`}
                                            ></i>
                                        </div>
                                    </button>
                                );
                            })}
                        </div>
                    ) : (
                        <div className="packing-empty">
                            <i className="fas fa-box-open"></i>
                            <p>No packaging levels defined.</p>
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

export default PackingModal;
