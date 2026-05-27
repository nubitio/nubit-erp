import React, { useCallback, useContext, useEffect, useId, useMemo, useRef, useState } from "react";
import { createPortal } from "react-dom";
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
//#region assets/ui/AppToolbar.tsx
const AppToolbar = ({ title, additionalToolbarContent, showRefreshButton = true, onRefresh, icon, refreshLabel = "Refresh", children }) => {
	return /* @__PURE__ */ jsxs("div", {
		className: "view-wrapper view-wrapper-dashboard",
		children: [/* @__PURE__ */ jsxs("div", {
			className: "app-toolbar theme-dependent",
			children: [
				/* @__PURE__ */ jsxs("div", {
					className: "app-toolbar__start",
					children: [icon && /* @__PURE__ */ jsx("i", {
						className: icon,
						"aria-hidden": "true"
					}), /* @__PURE__ */ jsx("span", {
						className: "toolbar-header",
						children: title
					})]
				}),
				/* @__PURE__ */ jsx("div", {
					className: "app-toolbar__center",
					children: additionalToolbarContent
				}),
				/* @__PURE__ */ jsx("div", {
					className: "app-toolbar__end",
					children: showRefreshButton && /* @__PURE__ */ jsx("button", {
						type: "button",
						className: "app-icon-button",
						title: refreshLabel,
						"aria-label": refreshLabel,
						onClick: onRefresh,
						children: /* @__PURE__ */ jsx("i", {
							className: "ph ph-arrow-clockwise",
							"aria-hidden": "true"
						})
					})
				})
			]
		}), children]
	});
};
//#endregion
//#region assets/ui/Card.tsx
const Card = ({ title, description, children }) => {
	return /* @__PURE__ */ jsx("div", {
		className: "app-card",
		children: /* @__PURE__ */ jsxs("div", {
			className: "app-card__content content",
			children: [/* @__PURE__ */ jsxs("div", {
				className: "header",
				children: [/* @__PURE__ */ jsx("div", {
					className: "title",
					children: title
				}), /* @__PURE__ */ jsx("div", {
					className: "description",
					children: description
				})]
			}), children]
		})
	});
};
//#endregion
//#region assets/ui/theme/ThemeProvider.tsx
const ThemeContext = React.createContext(null);
function useTheme() {
	const ctx = useContext(ThemeContext);
	if (!ctx) throw new Error("useTheme must be used inside ThemeProvider");
	return ctx;
}
const themeModes = [
	"light",
	"dark",
	"auto"
];
function getNextThemeMode(mode) {
	return themeModes[(themeModes.indexOf(mode) + 1) % themeModes.length];
}
function resolveTheme(mode) {
	if (mode !== "auto") return mode;
	return window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
}
function setThemeToDOM(href) {
	return new Promise((resolve) => {
		const id = "theme-link";
		let link = document.getElementById(id);
		if (link) {
			if (link.href.endsWith(href)) {
				resolve();
				return;
			}
			link.href = href;
		} else {
			link = document.createElement("link");
			link.id = id;
			link.rel = "stylesheet";
			link.href = href;
			document.head.appendChild(link);
		}
		link.onload = () => resolve();
	});
}
const ThemeProvider = ({ storageKey = "app-theme", themePrefix = "app-theme-", basePath = "/themes/", children }) => {
	const getPersistedMode = () => {
		const persisted = window.localStorage.getItem(storageKey);
		return persisted && themeModes.includes(persisted) ? persisted : "auto";
	};
	const [mode, setMode] = useState(getPersistedMode);
	const [isLoaded, setIsLoaded] = useState(false);
	const theme = useMemo(() => resolveTheme(mode), [mode]);
	useEffect(() => {
		setThemeToDOM(`${basePath}${themePrefix}${theme}.css`).then(() => setIsLoaded(true));
	}, [
		theme,
		basePath,
		themePrefix
	]);
	useEffect(() => {
		if (!isLoaded) return;
		const meta = document.querySelector("meta[name=\"theme-color\"]");
		if (meta) {
			const baseBg = getComputedStyle(document.body).getPropertyValue("--base-bg").trim();
			meta.setAttribute("content", baseBg || (theme === "dark" ? "#000000" : "#ffffff"));
		}
	}, [theme, isLoaded]);
	useEffect(() => {
		const mq = window.matchMedia("(prefers-color-scheme: dark)");
		const apply = () => {
			if (mode === "auto") setThemeToDOM(`${basePath}${themePrefix}${resolveTheme("auto")}.css`);
		};
		mq.addEventListener("change", apply);
		return () => mq.removeEventListener("change", apply);
	}, [
		mode,
		basePath,
		themePrefix
	]);
	const switchTheme = useCallback(() => {
		setMode((prev) => {
			const next = getNextThemeMode(prev);
			window.localStorage.setItem(storageKey, next);
			return next;
		});
	}, [storageKey]);
	const value = useMemo(() => ({
		theme,
		mode,
		switchTheme,
		isLoaded
	}), [
		theme,
		mode,
		switchTheme,
		isLoaded
	]);
	return /* @__PURE__ */ jsx(ThemeContext.Provider, {
		value,
		children
	});
};
//#endregion
//#region assets/ui/theme/ThemeSwitcher.tsx
const iconByMode = {
	light: "ph ph-sun",
	dark: "ph ph-moon",
	auto: "ph ph-desktop"
};
const labelByMode = {
	light: "Theme: Light",
	dark: "Theme: Dark",
	auto: "Theme: System"
};
const ThemeSwitcher = ({ labelByMode: overrides } = {}) => {
	const { mode, switchTheme } = useTheme();
	const label = {
		...labelByMode,
		...overrides
	}[mode];
	return /* @__PURE__ */ jsx("div", { children: /* @__PURE__ */ jsx("button", {
		className: "app-icon-button theme-button",
		type: "button",
		"aria-label": label,
		title: label,
		onClick: switchTheme,
		children: /* @__PURE__ */ jsx("i", {
			className: iconByMode[mode],
			"aria-hidden": "true"
		})
	}) });
};
//#endregion
export { AppDialog, AppToolbar, Card, ConfirmDialog, ThemeContext, ThemeProvider, ThemeSwitcher, useTheme };
