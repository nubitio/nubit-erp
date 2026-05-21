import { useEffect, useId, useRef } from "react";
import { createPortal } from "react-dom";
import "./AppDialog.scss";
import { Fragment, jsx, jsxs } from "react/jsx-runtime";
import { useTranslation } from "react-i18next";
//#region assets/ui/AppDialog.tsx
const formatSize = (value) => {
	if (value === void 0) return void 0;
	return typeof value === "number" ? `${value}px` : value;
};
const joinClassNames = (...values) => values.filter(Boolean).join(" ");
const AppDialog = ({ visible, open, title, onClose, children, footer, width, maxWidth, height, fullScreen = false, showCloseButton = true, closeOnOutsideClick = true, closeOnEscape = true, restoreFocus = true, keepMounted = false, rootClassName, className, headerClassName, titleClassName, bodyClassName, footerClassName, role = "dialog" }) => {
	const isVisible = visible ?? open ?? false;
	const titleId = useId();
	const dialogRef = useRef(null);
	const lastFocusedElementRef = useRef(null);
	const restoreFocusRef = useRef(restoreFocus);
	useEffect(() => {
		restoreFocusRef.current = restoreFocus;
	}, [restoreFocus]);
	useEffect(() => {
		if (!isVisible) return;
		lastFocusedElementRef.current = document.activeElement;
		window.setTimeout(() => {
			(dialogRef.current?.querySelector("button:not(:disabled), [href], input:not(:disabled), select:not(:disabled), textarea:not(:disabled), [tabindex]:not([tabindex=\"-1\"])"))?.focus();
		});
		return () => {
			if (restoreFocusRef.current) lastFocusedElementRef.current?.focus?.();
		};
	}, [isVisible]);
	useEffect(() => {
		if (!isVisible || !closeOnEscape) return;
		const handleKeyDown = (event) => {
			if (event.key === "Escape") {
				event.preventDefault();
				onClose();
			}
		};
		document.addEventListener("keydown", handleKeyDown);
		return () => document.removeEventListener("keydown", handleKeyDown);
	}, [
		closeOnEscape,
		onClose,
		isVisible
	]);
	if (!isVisible && !keepMounted) return null;
	return createPortal(/* @__PURE__ */ jsxs("div", {
		className: joinClassNames("app-dialog-root", rootClassName),
		"aria-hidden": !isVisible,
		style: {
			pointerEvents: isVisible ? void 0 : "none",
			visibility: isVisible ? void 0 : "hidden"
		},
		children: [/* @__PURE__ */ jsx("button", {
			className: "app-dialog__scrim",
			type: "button",
			"aria-label": "Cerrar diálogo",
			onClick: closeOnOutsideClick ? onClose : void 0
		}), /* @__PURE__ */ jsxs("div", {
			className: joinClassNames("app-dialog", fullScreen && "app-dialog--fullscreen", className),
			role,
			"aria-modal": "true",
			"aria-labelledby": titleId,
			ref: dialogRef,
			onKeyDownCapture: (event) => {
				if (!closeOnEscape && event.key === "Escape") {
					event.preventDefault();
					event.stopPropagation();
				}
			},
			style: {
				width: formatSize(width),
				maxWidth: formatSize(maxWidth),
				height: formatSize(height)
			},
			children: [
				/* @__PURE__ */ jsxs("div", {
					className: joinClassNames("app-dialog__header", headerClassName),
					children: [/* @__PURE__ */ jsx("h2", {
						className: joinClassNames("app-dialog__title", titleClassName),
						id: titleId,
						children: title
					}), showCloseButton && /* @__PURE__ */ jsx("button", {
						className: "app-icon-button app-dialog__close",
						type: "button",
						"aria-label": "Cerrar",
						onClick: onClose,
						children: /* @__PURE__ */ jsx("i", {
							className: "ph ph-x",
							"aria-hidden": "true"
						})
					})]
				}),
				/* @__PURE__ */ jsx("div", {
					className: joinClassNames("app-dialog__body", bodyClassName),
					children
				}),
				footer && /* @__PURE__ */ jsx("div", {
					className: joinClassNames("app-dialog__footer", footerClassName),
					children: footer
				})
			]
		})]
	}), document.body);
};
//#endregion
//#region assets/ui/ConfirmDialog.tsx
function ConfirmDialog({ open, title, message, onConfirm, onCancel, confirmLabel, cancelLabel, variant = "warning" }) {
	const { t } = useTranslation();
	const resolvedConfirmLabel = confirmLabel ?? t("common.confirm");
	return /* @__PURE__ */ jsx(AppDialog, {
		open,
		title,
		onClose: onCancel,
		maxWidth: 420,
		role: "alertdialog",
		footer: /* @__PURE__ */ jsxs(Fragment, { children: [/* @__PURE__ */ jsx("button", {
			type: "button",
			className: "app-button app-button--secondary",
			onClick: onCancel,
			autoFocus: true,
			children: cancelLabel ?? t("common.cancel")
		}), /* @__PURE__ */ jsx("button", {
			type: "button",
			className: variant === "danger" ? "app-button app-button--danger" : "app-button app-button--primary",
			onClick: onConfirm,
			children: resolvedConfirmLabel
		})] }),
		children: /* @__PURE__ */ jsx("p", {
			style: {
				margin: "0.5rem 0 1rem",
				lineHeight: 1.5
			},
			children: message
		})
	});
}
//#endregion
export { AppDialog, ConfirmDialog };
