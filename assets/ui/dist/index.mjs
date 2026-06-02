import React, { createContext, forwardRef, useCallback, useContext, useEffect, useId, useMemo, useRef, useState } from "react";
import { createPortal } from "react-dom";
import { Fragment, jsx, jsxs } from "react/jsx-runtime";
import { useTranslation } from "react-i18next";
//#region assets/ui/Button.tsx
const cx$1 = (...values) => values.filter(Boolean).join(" ");
const Button = ({ variant = "secondary", size = "md", fullWidth = false, icon, loading = false, className, children, disabled, ...props }) => /* @__PURE__ */ jsxs("button", {
	...props,
	type: props.type ?? "button",
	disabled: disabled || loading,
	"aria-busy": loading || void 0,
	className: cx$1("app-button", `app-button--${variant}`, size === "sm" && "app-button--sm", fullWidth && "app-button--full", className),
	children: [loading ? /* @__PURE__ */ jsx("span", {
		className: "app-button-spinner",
		"aria-hidden": "true"
	}) : icon ? /* @__PURE__ */ jsx("i", {
		className: icon,
		"aria-hidden": "true"
	}) : null, children]
});
const IconButton = ({ icon, label, variant = "default", className, ...props }) => /* @__PURE__ */ jsx("button", {
	...props,
	type: props.type ?? "button",
	"aria-label": props["aria-label"] ?? label,
	title: props.title ?? label,
	className: cx$1("app-icon-button", variant === "danger" && "app-icon-button--danger", className),
	children: /* @__PURE__ */ jsx("i", {
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
					}), showCloseButton && /* @__PURE__ */ jsx(IconButton, {
						className: "app-dialog__close",
						icon: "ph ph-x",
						label: "Cerrar",
						onClick: onClose
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
		footer: /* @__PURE__ */ jsxs(Fragment, { children: [/* @__PURE__ */ jsx(Button, {
			variant: "secondary",
			onClick: onCancel,
			autoFocus: true,
			children: cancelLabel ?? t("common.cancel")
		}), /* @__PURE__ */ jsx(Button, {
			variant: variant === "danger" ? "danger" : "primary",
			onClick: onConfirm,
			children: resolvedConfirmLabel
		})] }),
		children: /* @__PURE__ */ jsx("p", {
			className: "confirm-dialog__message",
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
					children: showRefreshButton && /* @__PURE__ */ jsx(IconButton, {
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
//#region assets/ui/FormControls.tsx
const cx = (...values) => values.filter(Boolean).join(" ");
const FormField = ({ label, error, helpText, children, className, ...props }) => /* @__PURE__ */ jsxs("label", {
	...props,
	className: cx("app-form-field", !!error && "app-form-field--error", className),
	children: [
		label && /* @__PURE__ */ jsx("span", {
			className: "app-form-field__label",
			children: label
		}),
		children,
		error ? /* @__PURE__ */ jsx("span", {
			className: "app-form-field__error",
			role: "alert",
			children: error
		}) : helpText ? /* @__PURE__ */ jsx("span", {
			className: "app-form-field__help",
			children: helpText
		}) : null
	]
});
const TextField = forwardRef(function TextField({ className, invalid, ...props }, ref) {
	return /* @__PURE__ */ jsx("input", {
		...props,
		ref,
		className: cx("app-input", invalid && "app-input--invalid", className)
	});
});
const SelectField = forwardRef(function SelectField({ className, invalid, children, ...props }, ref) {
	return /* @__PURE__ */ jsx("select", {
		...props,
		ref,
		className: cx("app-input", "app-select", invalid && "app-input--invalid", className),
		children
	});
});
const TextAreaField = forwardRef(function TextAreaField({ className, invalid, ...props }, ref) {
	return /* @__PURE__ */ jsx("textarea", {
		...props,
		ref,
		className: cx("app-input", "app-textarea", invalid && "app-input--invalid", className)
	});
});
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
	return /* @__PURE__ */ jsx("div", { children: /* @__PURE__ */ jsx(IconButton, {
		className: "theme-button",
		icon: iconByMode[mode],
		label,
		onClick: switchTheme
	}) });
};
//#endregion
//#region assets/ui/theme/DensityProvider.tsx
const DensityContext = createContext(null);
function useDensity() {
	const ctx = useContext(DensityContext);
	if (!ctx) throw new Error("useDensity must be used inside DensityProvider");
	return ctx;
}
const STORAGE_KEY$1 = "app-density";
const DensityProvider = ({ children }) => {
	const [density, setDensity] = useState(() => {
		return localStorage.getItem(STORAGE_KEY$1) === "compact" ? "compact" : "normal";
	});
	useEffect(() => {
		if (density === "compact") document.documentElement.dataset.density = "compact";
		else delete document.documentElement.dataset.density;
		localStorage.setItem(STORAGE_KEY$1, density);
	}, [density]);
	const toggleDensity = useCallback(() => {
		setDensity((prev) => prev === "normal" ? "compact" : "normal");
	}, []);
	return /* @__PURE__ */ jsx(DensityContext.Provider, {
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
	const [accent, setAccent] = useState(() => {
		return localStorage.getItem(STORAGE_KEY) ?? DEFAULT_ACCENT;
	});
	useEffect(() => {
		applyAccent(accent);
	}, [accent]);
	return {
		accent,
		changeAccent: useCallback((color) => {
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
	return /* @__PURE__ */ jsxs("div", {
		className: "settings-panel",
		role: "menu",
		"aria-label": "Display settings",
		children: [
			/* @__PURE__ */ jsx("p", {
				className: "settings-panel__heading",
				children: "Accent color"
			}),
			/* @__PURE__ */ jsx("div", {
				className: "settings-panel__swatches",
				role: "group",
				"aria-label": "Accent color presets",
				children: presets.map((preset) => /* @__PURE__ */ jsx("button", {
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
			/* @__PURE__ */ jsx("hr", { className: "settings-panel__divider" }),
			/* @__PURE__ */ jsx("p", {
				className: "settings-panel__heading",
				children: "Density"
			}),
			/* @__PURE__ */ jsxs("div", {
				className: "settings-panel__density",
				role: "group",
				"aria-label": "UI density",
				children: [/* @__PURE__ */ jsxs("button", {
					className: "settings-panel__density-btn",
					type: "button",
					"aria-pressed": density === "normal",
					onClick: () => density !== "normal" && toggleDensity(),
					children: [/* @__PURE__ */ jsx("i", {
						className: "ph ph-rows",
						"aria-hidden": "true"
					}), "Normal"]
				}), /* @__PURE__ */ jsxs("button", {
					className: "settings-panel__density-btn",
					type: "button",
					"aria-pressed": density === "compact",
					onClick: () => density !== "compact" && toggleDensity(),
					children: [/* @__PURE__ */ jsx("i", {
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
	return /* @__PURE__ */ jsx("span", {
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
	if (variant === "text" && lines > 1) return /* @__PURE__ */ jsx("div", {
		className: "app-skeleton-lines",
		children: Array.from({ length: lines }).map((_, i) => /* @__PURE__ */ jsx("span", {
			className: base,
			style: { width: i === lines - 1 ? "70%" : fmtSize(width) ?? "100%" },
			"aria-hidden": "true"
		}, i))
	});
	return /* @__PURE__ */ jsx("span", {
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
	return /* @__PURE__ */ jsxs("div", {
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
			/* @__PURE__ */ jsx("div", {
				className: "app-empty-state__icon-wrap",
				children: /* @__PURE__ */ jsx("i", {
					className: `ph ph-${iconName}`,
					"aria-hidden": "true"
				})
			}),
			/* @__PURE__ */ jsx("p", {
				className: "app-empty-state__title",
				children: title
			}),
			description && /* @__PURE__ */ jsx("p", {
				className: "app-empty-state__description",
				children: description
			}),
			action && /* @__PURE__ */ jsx("div", {
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
	return /* @__PURE__ */ jsxs("button", {
		type: "button",
		role: "checkbox",
		"aria-checked": active,
		"aria-pressed": active,
		disabled,
		className: `app-chip${size !== "md" ? ` app-chip--${size}` : ""}${active ? " is-active" : ""}${className ? ` ${className}` : ""}`,
		onClick,
		children: [
			icon && /* @__PURE__ */ jsx("i", {
				className: icon,
				"aria-hidden": "true"
			}),
			label,
			count !== void 0 && /* @__PURE__ */ jsx("span", {
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
	const id = useId();
	const input = /* @__PURE__ */ jsxs("span", {
		className: `app-toggle${size === "sm" ? " app-toggle--sm" : ""}${className ? ` ${className}` : ""}`,
		children: [/* @__PURE__ */ jsx("input", {
			id,
			type: "checkbox",
			checked,
			disabled,
			"aria-label": !label ? ariaLabel : void 0,
			onChange: (e) => onChange(e.target.checked)
		}), /* @__PURE__ */ jsx("span", {
			className: "app-toggle__track",
			"aria-hidden": "true"
		})]
	});
	if (!label) return input;
	return /* @__PURE__ */ jsxs("label", {
		htmlFor: id,
		className: "app-toggle-row",
		children: [/* @__PURE__ */ jsx("span", { children: label }), input]
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
	const [open, setOpen] = useState(false);
	const containerRef = useRef(null);
	useEffect(() => {
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
	return /* @__PURE__ */ jsxs("div", {
		ref: containerRef,
		className: `app-context-menu${className ? ` ${className}` : ""}`,
		children: [trigger ? /* @__PURE__ */ jsx("span", {
			onClick: () => setOpen((v) => !v),
			children: trigger
		}) : /* @__PURE__ */ jsx(IconButton, {
			icon: "ph ph-dots-three",
			label: triggerLabel,
			"aria-haspopup": "menu",
			"aria-expanded": open,
			onClick: () => setOpen((v) => !v)
		}), open && /* @__PURE__ */ jsx("ul", {
			className: "app-context-menu__dropdown",
			role: "menu",
			style: dropdownStyle,
			children: items.map((item, idx) => /* @__PURE__ */ jsxs(React.Fragment, { children: [item.separator && /* @__PURE__ */ jsx("li", {
				role: "none",
				"aria-hidden": "true",
				children: /* @__PURE__ */ jsx("div", { className: "app-context-menu__separator" })
			}), /* @__PURE__ */ jsx("li", {
				role: "none",
				children: /* @__PURE__ */ jsxs("button", {
					type: "button",
					role: "menuitem",
					disabled: item.disabled,
					"aria-disabled": item.disabled,
					className: `app-context-menu__item${item.danger ? " app-context-menu__item--danger" : ""}`,
					onClick: () => {
						setOpen(false);
						item.onClick?.();
					},
					children: [item.icon && /* @__PURE__ */ jsx("i", {
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
const StatCard = ({ title, headerExtra, menuVisible = true, menuItems = [{ label: "Configure" }, { label: "Remove" }], isLoading = false, className = "", children }) => /* @__PURE__ */ jsxs("div", {
	className: `stat-card${className ? ` ${className}` : ""}`,
	children: [/* @__PURE__ */ jsxs("div", {
		className: "stat-card__header",
		children: [/* @__PURE__ */ jsxs("div", { children: [title && /* @__PURE__ */ jsx("span", {
			className: "stat-card__title",
			children: title
		}), headerExtra && /* @__PURE__ */ jsx("span", {
			className: "stat-card__extra",
			children: headerExtra
		})] }), menuVisible && /* @__PURE__ */ jsx("span", {
			className: "stat-card__menu",
			children: /* @__PURE__ */ jsx(ContextMenu, { items: menuItems })
		})]
	}), !isLoading && /* @__PURE__ */ jsx("div", {
		className: "stat-card__body",
		children
	})]
});
//#endregion
export { ACCENT_PRESETS, AppDialog, AppToolbar, Badge, Button, Card, Chip, ConfirmDialog, ContextMenu, DensityContext, DensityProvider, EmptyState, FormField, IconButton, SelectField, SettingsPanel, Skeleton, StatCard, TextAreaField, TextField, ThemeContext, ThemeProvider, ThemeSwitcher, Toggle, useAccentColor, useDensity, useTheme };
