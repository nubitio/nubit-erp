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
react = __toESM(react, 1);
let react_dom = require("react-dom");
let react_jsx_runtime = require("react/jsx-runtime");
let react_i18next = require("react-i18next");
//#region assets/ui/Button.tsx
const cx$1 = (...values) => values.filter(Boolean).join(" ");
const Button = ({ variant = "secondary", size = "md", fullWidth = false, icon, loading = false, className, children, disabled, ...props }) => /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("button", {
	...props,
	type: props.type ?? "button",
	disabled: disabled || loading,
	"aria-busy": loading || void 0,
	className: cx$1("app-button", `app-button--${variant}`, size === "sm" && "app-button--sm", fullWidth && "app-button--full", className),
	children: [loading ? /* @__PURE__ */ (0, react_jsx_runtime.jsx)("span", {
		className: "app-button-spinner",
		"aria-hidden": "true"
	}) : icon ? /* @__PURE__ */ (0, react_jsx_runtime.jsx)("i", {
		className: icon,
		"aria-hidden": "true"
	}) : null, children]
});
const IconButton = ({ icon, label, variant = "default", className, ...props }) => /* @__PURE__ */ (0, react_jsx_runtime.jsx)("button", {
	...props,
	type: props.type ?? "button",
	"aria-label": props["aria-label"] ?? label,
	title: props.title ?? label,
	className: cx$1("app-icon-button", variant === "danger" && "app-icon-button--danger", className),
	children: /* @__PURE__ */ (0, react_jsx_runtime.jsx)("i", {
		className: icon,
		"aria-hidden": "true"
	})
});
//#endregion
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
					}), showCloseButton && /* @__PURE__ */ (0, react_jsx_runtime.jsx)(IconButton, {
						className: "app-dialog__close",
						icon: "ph ph-x",
						label: "Cerrar",
						onClick: onClose
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
		footer: /* @__PURE__ */ (0, react_jsx_runtime.jsxs)(react_jsx_runtime.Fragment, { children: [/* @__PURE__ */ (0, react_jsx_runtime.jsx)(Button, {
			variant: "secondary",
			onClick: onCancel,
			autoFocus: true,
			children: cancelLabel ?? t("common.cancel")
		}), /* @__PURE__ */ (0, react_jsx_runtime.jsx)(Button, {
			variant: variant === "danger" ? "danger" : "primary",
			onClick: onConfirm,
			children: resolvedConfirmLabel
		})] }),
		children: /* @__PURE__ */ (0, react_jsx_runtime.jsx)("p", {
			className: "confirm-dialog__message",
			children: message
		})
	});
}
//#endregion
//#region assets/ui/AppToolbar.tsx
const AppToolbar = ({ title, additionalToolbarContent, showRefreshButton = true, onRefresh, icon, refreshLabel = "Refresh", children }) => {
	return /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", {
		className: "view-wrapper view-wrapper-dashboard",
		children: [/* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", {
			className: "app-toolbar theme-dependent",
			children: [
				/* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", {
					className: "app-toolbar__start",
					children: [icon && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("i", {
						className: icon,
						"aria-hidden": "true"
					}), /* @__PURE__ */ (0, react_jsx_runtime.jsx)("span", {
						className: "toolbar-header",
						children: title
					})]
				}),
				/* @__PURE__ */ (0, react_jsx_runtime.jsx)("div", {
					className: "app-toolbar__center",
					children: additionalToolbarContent
				}),
				/* @__PURE__ */ (0, react_jsx_runtime.jsx)("div", {
					className: "app-toolbar__end",
					children: showRefreshButton && /* @__PURE__ */ (0, react_jsx_runtime.jsx)(IconButton, {
						icon: "ph ph-arrow-clockwise",
						label: refreshLabel,
						onClick: onRefresh
					})
				})
			]
		}), children]
	});
};
//#endregion
//#region assets/ui/Card.tsx
const Card = ({ title, description, children }) => {
	return /* @__PURE__ */ (0, react_jsx_runtime.jsx)("div", {
		className: "app-card",
		children: /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", {
			className: "app-card__content content",
			children: [/* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", {
				className: "header",
				children: [/* @__PURE__ */ (0, react_jsx_runtime.jsx)("div", {
					className: "title",
					children: title
				}), /* @__PURE__ */ (0, react_jsx_runtime.jsx)("div", {
					className: "description",
					children: description
				})]
			}), children]
		})
	});
};
//#endregion
//#region assets/ui/FormControls.tsx
const cx = (...values) => values.filter(Boolean).join(" ");
const FormField = ({ label, error, helpText, children, className, ...props }) => /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("label", {
	...props,
	className: cx("app-form-field", !!error && "app-form-field--error", className),
	children: [
		label && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("span", {
			className: "app-form-field__label",
			children: label
		}),
		children,
		error ? /* @__PURE__ */ (0, react_jsx_runtime.jsx)("span", {
			className: "app-form-field__error",
			role: "alert",
			children: error
		}) : helpText ? /* @__PURE__ */ (0, react_jsx_runtime.jsx)("span", {
			className: "app-form-field__help",
			children: helpText
		}) : null
	]
});
const TextField = (0, react.forwardRef)(function TextField({ className, invalid, ...props }, ref) {
	return /* @__PURE__ */ (0, react_jsx_runtime.jsx)("input", {
		...props,
		ref,
		className: cx("app-input", invalid && "app-input--invalid", className)
	});
});
const SelectField = (0, react.forwardRef)(function SelectField({ className, invalid, children, ...props }, ref) {
	return /* @__PURE__ */ (0, react_jsx_runtime.jsx)("select", {
		...props,
		ref,
		className: cx("app-input", "app-select", invalid && "app-input--invalid", className),
		children
	});
});
const TextAreaField = (0, react.forwardRef)(function TextAreaField({ className, invalid, ...props }, ref) {
	return /* @__PURE__ */ (0, react_jsx_runtime.jsx)("textarea", {
		...props,
		ref,
		className: cx("app-input", "app-textarea", invalid && "app-input--invalid", className)
	});
});
//#endregion
//#region assets/ui/theme/ThemeProvider.tsx
const ThemeContext = react.default.createContext(null);
function useTheme() {
	const ctx = (0, react.useContext)(ThemeContext);
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
const THEME_VERSION = typeof __THEME_VERSION__ !== "undefined" ? __THEME_VERSION__ : String(Date.now());
function setThemeToDOM(href) {
	const versioned = `${href}?v=${THEME_VERSION}`;
	return new Promise((resolve) => {
		const id = "theme-link";
		let link = document.getElementById(id);
		if (link) {
			if (link.getAttribute("data-theme-href") === href) {
				resolve();
				return;
			}
			link.href = versioned;
			link.setAttribute("data-theme-href", href);
		} else {
			link = document.createElement("link");
			link.id = id;
			link.rel = "stylesheet";
			link.href = versioned;
			link.setAttribute("data-theme-href", href);
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
	const [mode, setMode] = (0, react.useState)(getPersistedMode);
	const [isLoaded, setIsLoaded] = (0, react.useState)(false);
	const theme = (0, react.useMemo)(() => resolveTheme(mode), [mode]);
	(0, react.useEffect)(() => {
		setThemeToDOM(`${basePath}${themePrefix}${theme}.css`).then(() => setIsLoaded(true));
	}, [
		theme,
		basePath,
		themePrefix
	]);
	(0, react.useEffect)(() => {
		if (!isLoaded) return;
		const meta = document.querySelector("meta[name=\"theme-color\"]");
		if (meta) {
			const baseBg = getComputedStyle(document.body).getPropertyValue("--base-bg").trim();
			meta.setAttribute("content", baseBg || (theme === "dark" ? "#000000" : "#ffffff"));
		}
	}, [theme, isLoaded]);
	(0, react.useEffect)(() => {
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
	const switchTheme = (0, react.useCallback)(() => {
		setMode((prev) => {
			const next = getNextThemeMode(prev);
			window.localStorage.setItem(storageKey, next);
			return next;
		});
	}, [storageKey]);
	const value = (0, react.useMemo)(() => ({
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
	return /* @__PURE__ */ (0, react_jsx_runtime.jsx)(ThemeContext.Provider, {
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
	return /* @__PURE__ */ (0, react_jsx_runtime.jsx)("div", { children: /* @__PURE__ */ (0, react_jsx_runtime.jsx)(IconButton, {
		className: "theme-button",
		icon: iconByMode[mode],
		label,
		onClick: switchTheme
	}) });
};
//#endregion
//#region assets/ui/theme/DensityProvider.tsx
const DensityContext = (0, react.createContext)(null);
function useDensity() {
	const ctx = (0, react.useContext)(DensityContext);
	if (!ctx) throw new Error("useDensity must be used inside DensityProvider");
	return ctx;
}
const STORAGE_KEY$1 = "app-density";
const DensityProvider = ({ children }) => {
	const [density, setDensity] = (0, react.useState)(() => {
		return localStorage.getItem(STORAGE_KEY$1) === "compact" ? "compact" : "normal";
	});
	(0, react.useEffect)(() => {
		if (density === "compact") document.documentElement.dataset.density = "compact";
		else delete document.documentElement.dataset.density;
		localStorage.setItem(STORAGE_KEY$1, density);
	}, [density]);
	const toggleDensity = (0, react.useCallback)(() => {
		setDensity((prev) => prev === "normal" ? "compact" : "normal");
	}, []);
	return /* @__PURE__ */ (0, react_jsx_runtime.jsx)(DensityContext.Provider, {
		value: {
			density,
			toggleDensity
		},
		children
	});
};
//#endregion
//#region assets/ui/theme/useAccentColor.ts
const ACCENT_PRESETS = [
	{
		label: "Blue",
		value: "#2563eb"
	},
	{
		label: "Indigo",
		value: "#4f46e5"
	},
	{
		label: "Violet",
		value: "#7c3aed"
	},
	{
		label: "Teal",
		value: "#0d9488"
	},
	{
		label: "Rose",
		value: "#e11d48"
	},
	{
		label: "Amber",
		value: "#d97706"
	},
	{
		label: "Slate",
		value: "#475569"
	}
];
const STORAGE_KEY = "app-accent-color";
const DEFAULT_ACCENT = "#2563eb";
function applyAccent(color) {
	document.documentElement.style.setProperty("--accent-color", color);
}
function useAccentColor() {
	const [accent, setAccent] = (0, react.useState)(() => {
		return localStorage.getItem(STORAGE_KEY) ?? DEFAULT_ACCENT;
	});
	(0, react.useEffect)(() => {
		applyAccent(accent);
	}, [accent]);
	return {
		accent,
		changeAccent: (0, react.useCallback)((color) => {
			localStorage.setItem(STORAGE_KEY, color);
			setAccent(color);
		}, []),
		presets: ACCENT_PRESETS
	};
}
//#endregion
//#region assets/ui/SettingsPanel.tsx
const SettingsPanel = ({ onClose: _onClose }) => {
	const { density, toggleDensity } = useDensity();
	const { accent, changeAccent, presets } = useAccentColor();
	return /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", {
		className: "settings-panel",
		role: "menu",
		"aria-label": "Display settings",
		children: [
			/* @__PURE__ */ (0, react_jsx_runtime.jsx)("p", {
				className: "settings-panel__heading",
				children: "Accent color"
			}),
			/* @__PURE__ */ (0, react_jsx_runtime.jsx)("div", {
				className: "settings-panel__swatches",
				role: "group",
				"aria-label": "Accent color presets",
				children: presets.map((preset) => /* @__PURE__ */ (0, react_jsx_runtime.jsx)("button", {
					className: "settings-panel__swatch",
					type: "button",
					"aria-label": preset.label,
					"aria-pressed": accent === preset.value,
					title: preset.label,
					style: {
						background: preset.value,
						color: preset.value
					},
					onClick: () => changeAccent(preset.value)
				}, preset.value))
			}),
			/* @__PURE__ */ (0, react_jsx_runtime.jsx)("hr", { className: "settings-panel__divider" }),
			/* @__PURE__ */ (0, react_jsx_runtime.jsx)("p", {
				className: "settings-panel__heading",
				children: "Density"
			}),
			/* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", {
				className: "settings-panel__density",
				role: "group",
				"aria-label": "UI density",
				children: [/* @__PURE__ */ (0, react_jsx_runtime.jsxs)("button", {
					className: "settings-panel__density-btn",
					type: "button",
					"aria-pressed": density === "normal",
					onClick: () => density !== "normal" && toggleDensity(),
					children: [/* @__PURE__ */ (0, react_jsx_runtime.jsx)("i", {
						className: "ph ph-rows",
						"aria-hidden": "true"
					}), "Normal"]
				}), /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("button", {
					className: "settings-panel__density-btn",
					type: "button",
					"aria-pressed": density === "compact",
					onClick: () => density !== "compact" && toggleDensity(),
					children: [/* @__PURE__ */ (0, react_jsx_runtime.jsx)("i", {
						className: "ph ph-rows-plus-bottom",
						"aria-hidden": "true"
					}), "Compact"]
				})]
			})
		]
	});
};
//#endregion
//#region assets/ui/Badge.tsx
const Badge = ({ variant = "primary", size = "md", outlined = false, pill = true, dot = false, children, className }) => {
	return /* @__PURE__ */ (0, react_jsx_runtime.jsx)("span", {
		className: [
			"app-badge",
			`app-badge--${variant}`,
			size === "sm" && "app-badge--sm",
			outlined && "app-badge--outlined",
			pill && "app-badge--pill",
			dot && "app-badge--dot",
			className
		].filter(Boolean).join(" "),
		children: dot ? null : children
	});
};
//#endregion
//#region assets/ui/Skeleton.tsx
const fmtSize = (v) => v === void 0 ? void 0 : typeof v === "number" ? `${v}px` : v;
const Skeleton = ({ variant = "rect", width, height, lines = 1, className }) => {
	const base = [
		"app-skeleton",
		`app-skeleton--${variant}`,
		className
	].filter(Boolean).join(" ");
	if (variant === "text" && lines > 1) return /* @__PURE__ */ (0, react_jsx_runtime.jsx)("div", {
		className: "app-skeleton-lines",
		children: Array.from({ length: lines }).map((_, i) => /* @__PURE__ */ (0, react_jsx_runtime.jsx)("span", {
			className: base,
			style: { width: i === lines - 1 ? "70%" : fmtSize(width) ?? "100%" },
			"aria-hidden": "true"
		}, i))
	});
	return /* @__PURE__ */ (0, react_jsx_runtime.jsx)("span", {
		className: base,
		style: {
			width: fmtSize(width),
			height: fmtSize(height)
		},
		"aria-hidden": "true"
	});
};
//#endregion
//#region assets/ui/EmptyState.tsx
const DEFAULT_ICONS = {
	default: "folder-open",
	danger: "warning-circle",
	warning: "warning",
	success: "check-circle"
};
const EmptyState = ({ icon, variant = "default", title, description, action, size = "md", fill = false, className }) => {
	const iconName = icon ?? DEFAULT_ICONS[variant];
	return /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", {
		className: [
			"app-empty-state",
			`app-empty-state--${variant}`,
			`app-empty-state--${size}`,
			fill && "app-empty-state--fill",
			className
		].filter(Boolean).join(" "),
		role: "status",
		"aria-live": "polite",
		children: [
			/* @__PURE__ */ (0, react_jsx_runtime.jsx)("div", {
				className: "app-empty-state__icon-wrap",
				children: /* @__PURE__ */ (0, react_jsx_runtime.jsx)("i", {
					className: `ph ph-${iconName}`,
					"aria-hidden": "true"
				})
			}),
			/* @__PURE__ */ (0, react_jsx_runtime.jsx)("p", {
				className: "app-empty-state__title",
				children: title
			}),
			description && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("p", {
				className: "app-empty-state__description",
				children: description
			}),
			action && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("div", {
				className: "app-empty-state__action",
				children: action
			})
		]
	});
};
//#endregion
//#region assets/ui/Chip.tsx
/**
* Pill-shaped filter/tag chip. Stateless — caller controls `active`.
* Use for category filters, tag lists, and multi-select inputs.
*/
const Chip = ({ label, active = false, count, icon, size = "md", disabled = false, onClick, className = "" }) => {
	return /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("button", {
		type: "button",
		role: "checkbox",
		"aria-checked": active,
		"aria-pressed": active,
		disabled,
		className: `app-chip${size !== "md" ? ` app-chip--${size}` : ""}${active ? " is-active" : ""}${className ? ` ${className}` : ""}`,
		onClick,
		children: [
			icon && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("i", {
				className: icon,
				"aria-hidden": "true"
			}),
			label,
			count !== void 0 && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("span", {
				className: "app-chip__count",
				children: count
			})
		]
	});
};
//#endregion
//#region assets/ui/Toggle.tsx
/**
* Accessible toggle switch. Always controlled — pass `checked` + `onChange`.
* Renders a visually-hidden `<input type="checkbox">` with a custom track.
*
* With `label`: wraps in `.app-toggle-row` for a label ↔ toggle layout.
* Without `label`: renders bare `.app-toggle`.
*/
const Toggle = ({ checked, onChange, label, size = "md", disabled = false, "aria-label": ariaLabel, className = "" }) => {
	const id = (0, react.useId)();
	const input = /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("span", {
		className: `app-toggle${size === "sm" ? " app-toggle--sm" : ""}${className ? ` ${className}` : ""}`,
		children: [/* @__PURE__ */ (0, react_jsx_runtime.jsx)("input", {
			id,
			type: "checkbox",
			checked,
			disabled,
			"aria-label": !label ? ariaLabel : void 0,
			onChange: (e) => onChange(e.target.checked)
		}), /* @__PURE__ */ (0, react_jsx_runtime.jsx)("span", {
			className: "app-toggle__track",
			"aria-hidden": "true"
		})]
	});
	if (!label) return input;
	return /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("label", {
		htmlFor: id,
		className: "app-toggle-row",
		children: [/* @__PURE__ */ (0, react_jsx_runtime.jsx)("span", { children: label }), input]
	});
};
//#endregion
//#region assets/ui/ContextMenu.tsx
/**
* Three-dot overflow context menu.
* Replaces the legacy `shared/ui/library/card-menu/CardMenu` component.
*
* Features over CardMenu:
* - `danger` variant items
* - `separator` support
* - `icon` per item
* - Custom trigger slot
* - Left/right alignment
* - Click-outside + Escape to close
*/
const ContextMenu = ({ items, visible = true, trigger, triggerLabel = "Opciones", align = "right", className = "" }) => {
	const [open, setOpen] = (0, react.useState)(false);
	const containerRef = (0, react.useRef)(null);
	(0, react.useEffect)(() => {
		if (!open) return;
		const onMouseDown = (e) => {
			if (containerRef.current && !containerRef.current.contains(e.target)) setOpen(false);
		};
		const onKeyDown = (e) => {
			if (e.key === "Escape") setOpen(false);
		};
		document.addEventListener("mousedown", onMouseDown);
		document.addEventListener("keydown", onKeyDown);
		return () => {
			document.removeEventListener("mousedown", onMouseDown);
			document.removeEventListener("keydown", onKeyDown);
		};
	}, [open]);
	if (!visible) return null;
	const dropdownStyle = align === "left" ? {
		right: "auto",
		left: 0
	} : {};
	return /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", {
		ref: containerRef,
		className: `app-context-menu${className ? ` ${className}` : ""}`,
		children: [trigger ? /* @__PURE__ */ (0, react_jsx_runtime.jsx)("span", {
			onClick: () => setOpen((v) => !v),
			children: trigger
		}) : /* @__PURE__ */ (0, react_jsx_runtime.jsx)(IconButton, {
			icon: "ph ph-dots-three",
			label: triggerLabel,
			"aria-haspopup": "menu",
			"aria-expanded": open,
			onClick: () => setOpen((v) => !v)
		}), open && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("ul", {
			className: "app-context-menu__dropdown",
			role: "menu",
			style: dropdownStyle,
			children: items.map((item, idx) => /* @__PURE__ */ (0, react_jsx_runtime.jsxs)(react.default.Fragment, { children: [item.separator && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("li", {
				role: "none",
				"aria-hidden": "true",
				children: /* @__PURE__ */ (0, react_jsx_runtime.jsx)("div", { className: "app-context-menu__separator" })
			}), /* @__PURE__ */ (0, react_jsx_runtime.jsx)("li", {
				role: "none",
				children: /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("button", {
					type: "button",
					role: "menuitem",
					disabled: item.disabled,
					"aria-disabled": item.disabled,
					className: `app-context-menu__item${item.danger ? " app-context-menu__item--danger" : ""}`,
					onClick: () => {
						setOpen(false);
						item.onClick?.();
					},
					children: [item.icon && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("i", {
						className: item.icon,
						"aria-hidden": "true"
					}), item.label]
				})
			})] }, idx))
		})]
	});
};
//#endregion
//#region assets/ui/StatCard.tsx
/**
* General-purpose analytics / dashboard card.
* Replaces the legacy `shared/ui/card-analytics/CardAnalytics` component.
* Uses `ContextMenu` from `@nubit/ui` for the overflow menu.
*/
const StatCard = ({ title, headerExtra, menuVisible = true, menuItems = [{ label: "Configure" }, { label: "Remove" }], isLoading = false, className = "", children }) => /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", {
	className: `stat-card${className ? ` ${className}` : ""}`,
	children: [/* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", {
		className: "stat-card__header",
		children: [/* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", { children: [title && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("span", {
			className: "stat-card__title",
			children: title
		}), headerExtra && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("span", {
			className: "stat-card__extra",
			children: headerExtra
		})] }), menuVisible && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("span", {
			className: "stat-card__menu",
			children: /* @__PURE__ */ (0, react_jsx_runtime.jsx)(ContextMenu, { items: menuItems })
		})]
	}), !isLoading && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("div", {
		className: "stat-card__body",
		children
	})]
});
//#endregion
exports.ACCENT_PRESETS = ACCENT_PRESETS;
exports.AppDialog = AppDialog;
exports.AppToolbar = AppToolbar;
exports.Badge = Badge;
exports.Button = Button;
exports.Card = Card;
exports.Chip = Chip;
exports.ConfirmDialog = ConfirmDialog;
exports.ContextMenu = ContextMenu;
exports.DensityContext = DensityContext;
exports.DensityProvider = DensityProvider;
exports.EmptyState = EmptyState;
exports.FormField = FormField;
exports.IconButton = IconButton;
exports.SelectField = SelectField;
exports.SettingsPanel = SettingsPanel;
exports.Skeleton = Skeleton;
exports.StatCard = StatCard;
exports.TextAreaField = TextAreaField;
exports.TextField = TextField;
exports.ThemeContext = ThemeContext;
exports.ThemeProvider = ThemeProvider;
exports.ThemeSwitcher = ThemeSwitcher;
exports.Toggle = Toggle;
exports.useAccentColor = useAccentColor;
exports.useDensity = useDensity;
exports.useTheme = useTheme;
