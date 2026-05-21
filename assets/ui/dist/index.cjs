Object.defineProperty(exports, Symbol.toStringTag, { value: "Module" });
//#region \0rolldown/runtime.js
var __create = Object.create;
var __defProp = Object.defineProperty;
var __getOwnPropDesc = Object.getOwnPropertyDescriptor;
var __getOwnPropNames = Object.getOwnPropertyNames;
var __getProtoOf = Object.getPrototypeOf;
var __hasOwnProp = Object.prototype.hasOwnProperty;
var __copyProps = (to, from, except, desc) => {
	if (from && typeof from === "object" || typeof from === "function") for (var keys = __getOwnPropNames(from), i = 0, n = keys.length, key; i < n; i++) {
		key = keys[i];
		if (!__hasOwnProp.call(to, key) && key !== except) __defProp(to, key, {
			get: ((k) => from[k]).bind(null, key),
			enumerable: !(desc = __getOwnPropDesc(from, key)) || desc.enumerable
		});
	}
	return to;
};
var __toESM = (mod, isNodeMode, target) => (target = mod != null ? __create(__getProtoOf(mod)) : {}, __copyProps(isNodeMode || !mod || !mod.__esModule ? __defProp(target, "default", {
	value: mod,
	enumerable: true
}) : target, mod));
//#endregion
let react = require("react");
react = __toESM(react);
let react_dom = require("react-dom");
require("./AppDialog.scss");
let react_jsx_runtime = require("react/jsx-runtime");
let react_i18next = require("react-i18next");
//#region assets/ui/AppDialog.tsx
const formatSize = (value) => {
	if (value === void 0) return void 0;
	return typeof value === "number" ? `${value}px` : value;
};
const joinClassNames = (...values) => values.filter(Boolean).join(" ");
const AppDialog = ({ visible, open, title, onClose, children, footer, width, maxWidth, height, fullScreen = false, showCloseButton = true, closeOnOutsideClick = true, closeOnEscape = true, restoreFocus = true, keepMounted = false, rootClassName, className, headerClassName, titleClassName, bodyClassName, footerClassName, role = "dialog" }) => {
	const isVisible = visible ?? open ?? false;
	const titleId = (0, react.useId)();
	const dialogRef = (0, react.useRef)(null);
	const lastFocusedElementRef = (0, react.useRef)(null);
	const restoreFocusRef = (0, react.useRef)(restoreFocus);
	(0, react.useEffect)(() => {
		restoreFocusRef.current = restoreFocus;
	}, [restoreFocus]);
	(0, react.useEffect)(() => {
		if (!isVisible) return;
		lastFocusedElementRef.current = document.activeElement;
		window.setTimeout(() => {
			(dialogRef.current?.querySelector("button:not(:disabled), [href], input:not(:disabled), select:not(:disabled), textarea:not(:disabled), [tabindex]:not([tabindex=\"-1\"])"))?.focus();
		});
		return () => {
			if (restoreFocusRef.current) lastFocusedElementRef.current?.focus?.();
		};
	}, [isVisible]);
	(0, react.useEffect)(() => {
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
	return (0, react_dom.createPortal)(/* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", {
		className: joinClassNames("app-dialog-root", rootClassName),
		"aria-hidden": !isVisible,
		style: {
			pointerEvents: isVisible ? void 0 : "none",
			visibility: isVisible ? void 0 : "hidden"
		},
		children: [/* @__PURE__ */ (0, react_jsx_runtime.jsx)("button", {
			className: "app-dialog__scrim",
			type: "button",
			"aria-label": "Cerrar diálogo",
			onClick: closeOnOutsideClick ? onClose : void 0
		}), /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", {
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
				/* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", {
					className: joinClassNames("app-dialog__header", headerClassName),
					children: [/* @__PURE__ */ (0, react_jsx_runtime.jsx)("h2", {
						className: joinClassNames("app-dialog__title", titleClassName),
						id: titleId,
						children: title
					}), showCloseButton && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("button", {
						className: "app-icon-button app-dialog__close",
						type: "button",
						"aria-label": "Cerrar",
						onClick: onClose,
						children: /* @__PURE__ */ (0, react_jsx_runtime.jsx)("i", {
							className: "ph ph-x",
							"aria-hidden": "true"
						})
					})]
				}),
				/* @__PURE__ */ (0, react_jsx_runtime.jsx)("div", {
					className: joinClassNames("app-dialog__body", bodyClassName),
					children
				}),
				footer && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("div", {
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
	const { t } = (0, react_i18next.useTranslation)();
	const resolvedConfirmLabel = confirmLabel ?? t("common.confirm");
	return /* @__PURE__ */ (0, react_jsx_runtime.jsx)(AppDialog, {
		open,
		title,
		onClose: onCancel,
		maxWidth: 420,
		role: "alertdialog",
		footer: /* @__PURE__ */ (0, react_jsx_runtime.jsxs)(react_jsx_runtime.Fragment, { children: [/* @__PURE__ */ (0, react_jsx_runtime.jsx)("button", {
			type: "button",
			className: "app-button app-button--secondary",
			onClick: onCancel,
			autoFocus: true,
			children: cancelLabel ?? t("common.cancel")
		}), /* @__PURE__ */ (0, react_jsx_runtime.jsx)("button", {
			type: "button",
			className: variant === "danger" ? "app-button app-button--danger" : "app-button app-button--primary",
			onClick: onConfirm,
			children: resolvedConfirmLabel
		})] }),
		children: /* @__PURE__ */ (0, react_jsx_runtime.jsx)("p", {
			style: {
				margin: "0.5rem 0 1rem",
				lineHeight: 1.5
			},
			children: message
		})
	});
}
//#endregion
exports.AppDialog = AppDialog;
exports.ConfirmDialog = ConfirmDialog;
