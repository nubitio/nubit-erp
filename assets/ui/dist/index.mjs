import React, { createContext, forwardRef, useCallback, useContext, useEffect, useId, useMemo, useRef, useState } from "react";
import { Fragment, jsx, jsxs } from "react/jsx-runtime";
import { createPortal } from "react-dom";
import { useTranslation } from "react-i18next";
//#region assets/ui/Avatar.tsx
/**
* 8 semantic hues aligned with the Fluent / @nubit design palette.
* Expressed as HSL so the lightness can be adjusted for dark mode via CSS.
* The color is set as --avatar-hue on the element and consumed in Avatar.scss.
*/
const AVATAR_HUES = [
	218,
	148,
	262,
	192,
	36,
	203,
	22,
	340
];
const NAMED_SIZES = {
	xs: 20,
	sm: 24,
	md: 32,
	lg: 40,
	xl: 48
};
function getAvatarInitials(owner = "") {
	const normalized = owner.trim();
	if (!normalized) return "NA";
	const words = normalized.split(/\s+/);
	if (words.length >= 2) return `${words[0][0]}${words[1][0]}`.toUpperCase();
	return normalized.slice(0, 2).toUpperCase();
}
function getAvatarHue(owner = "") {
	let hash = 0;
	for (let i = 0; i < owner.length; i++) hash = hash * 31 + owner.charCodeAt(i) & 268435455;
	return AVATAR_HUES[Math.abs(hash) % AVATAR_HUES.length];
}
const Avatar = ({ owner = "", size, variant = "md", shape = "circle", alt, className }) => {
	const px = size ?? NAMED_SIZES[variant];
	const initials = getAvatarInitials(owner);
	const hue = getAvatarHue(owner);
	return /* @__PURE__ */ jsx("span", {
		className: [
			"app-avatar",
			shape === "square" && "app-avatar--square",
			className
		].filter(Boolean).join(" "),
		role: "img",
		"aria-label": alt ?? (owner || "Avatar"),
		style: {
			width: px,
			height: px,
			fontSize: Math.round(px * .38),
			"--avatar-hue": hue
		},
		children: initials
	});
};
//#endregion
//#region assets/ui/Button.tsx
const cx$3 = (...values) => values.filter(Boolean).join(" ");
const Button = ({ variant = "secondary", size = "md", fullWidth = false, icon, loading = false, className, children, disabled, ...props }) => /* @__PURE__ */ jsxs("button", {
	...props,
	type: props.type ?? "button",
	disabled: disabled || loading,
	"aria-busy": loading || void 0,
	className: cx$3("app-button", `app-button--${variant}`, size === "sm" && "app-button--sm", fullWidth && "app-button--full", className),
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
	className: cx$3("app-icon-button", variant === "danger" && "app-icon-button--danger", className),
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
const cx$2 = (...values) => values.filter(Boolean).join(" ");
const FormField = ({ label, error, helpText, children, className, ...props }) => /* @__PURE__ */ jsxs("label", {
	...props,
	className: cx$2("app-form-field", !!error && "app-form-field--error", className),
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
		className: cx$2("app-input", invalid && "app-input--invalid", className)
	});
});
const SelectField = forwardRef(function SelectField({ className, invalid, children, ...props }, ref) {
	return /* @__PURE__ */ jsx("select", {
		...props,
		ref,
		className: cx$2("app-input", "app-select", invalid && "app-input--invalid", className),
		children
	});
});
const TextAreaField = forwardRef(function TextAreaField({ className, invalid, ...props }, ref) {
	return /* @__PURE__ */ jsx("textarea", {
		...props,
		ref,
		className: cx$2("app-input", "app-textarea", invalid && "app-input--invalid", className)
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
const themeValues = ["light", "dark"];
function getNextThemeMode(mode) {
	return themeModes[(themeModes.indexOf(mode) + 1) % themeModes.length];
}
function getSystemTheme() {
	return window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
}
function resolveTheme(mode, systemTheme = getSystemTheme()) {
	if (mode !== "auto") return mode;
	return systemTheme;
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
function applyThemeAttributes(theme) {
	const root = document.documentElement;
	root.dataset.theme = theme;
	themeValues.forEach((value) => root.classList.remove(`app-theme-${value}`));
	root.classList.add(`app-theme-${theme}`);
}
const ThemeProvider = ({ storageKey = "app-theme", themePrefix = "app-theme-", basePath = "/themes/", children }) => {
	const getPersistedMode = () => {
		const persisted = window.localStorage.getItem(storageKey);
		return persisted && themeModes.includes(persisted) ? persisted : "auto";
	};
	const [mode, setMode] = useState(getPersistedMode);
	const [systemTheme, setSystemTheme] = useState(getSystemTheme);
	const [isLoaded, setIsLoaded] = useState(false);
	const theme = useMemo(() => resolveTheme(mode, systemTheme), [mode, systemTheme]);
	useEffect(() => {
		applyThemeAttributes(theme);
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
		const apply = () => setSystemTheme(mq.matches ? "dark" : "light");
		mq.addEventListener("change", apply);
		return () => mq.removeEventListener("change", apply);
	}, []);
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
		if (localStorage.getItem(STORAGE_KEY)) applyAccent(accent);
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
//#region assets/ui/CollapsibleSection.tsx
/**
* Accessible collapsible section with a labelled header trigger.
*
* - Uses `aria-expanded` and `aria-controls` for screen reader support.
* - The body is conditionally rendered (not just hidden) to avoid mounting
*   heavy children before they are needed. Pass `keepMounted` if you need
*   the body always in the DOM.
*/
const CollapsibleSection = ({ label, icon, open, onToggle, children, trailing, className, bodyClassName, "data-testid": testId }) => {
	const bodyId = useId();
	return /* @__PURE__ */ jsxs("div", {
		className: [
			"app-collapsible",
			open && "app-collapsible--open",
			className
		].filter(Boolean).join(" "),
		"data-testid": testId,
		children: [/* @__PURE__ */ jsxs("div", {
			className: "app-collapsible__header",
			children: [/* @__PURE__ */ jsxs("button", {
				type: "button",
				className: "app-collapsible__trigger",
				"aria-expanded": open,
				"aria-controls": bodyId,
				onClick: onToggle,
				children: [
					icon && /* @__PURE__ */ jsx("i", {
						className: icon,
						"aria-hidden": "true"
					}),
					/* @__PURE__ */ jsx("span", {
						className: "app-collapsible__label",
						children: label
					}),
					/* @__PURE__ */ jsx("i", {
						className: "ph ph-caret-down app-collapsible__chevron",
						"aria-hidden": "true"
					})
				]
			}), trailing && /* @__PURE__ */ jsx("div", {
				className: "app-collapsible__trailing",
				children: trailing
			})]
		}), /* @__PURE__ */ jsx("div", {
			id: bodyId,
			className: ["app-collapsible__body", bodyClassName].filter(Boolean).join(" "),
			role: "region",
			"aria-labelledby": void 0,
			hidden: !open,
			children
		})]
	});
};
//#endregion
//#region assets/ui/DatePicker.tsx
const cx$1 = (...values) => values.filter(Boolean).join(" ");
const ISO_DATE_RE$1 = /^\d{4}-\d{2}-\d{2}$/;
const getLocale$1 = (override) => override || document.documentElement.lang || navigator.language || "en-US";
const pad$1 = (value) => String(value).padStart(2, "0");
const toIsoDate$1 = (date) => `${date.getFullYear()}-${pad$1(date.getMonth() + 1)}-${pad$1(date.getDate())}`;
const parseIsoDate$1 = (value) => {
	if (!value || !ISO_DATE_RE$1.test(value)) return null;
	const [year, month, day] = value.split("-").map(Number);
	const date = new Date(year, month - 1, day);
	if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) return null;
	return date;
};
const startOfMonth$1 = (date) => new Date(date.getFullYear(), date.getMonth(), 1);
const addMonths$1 = (date, months) => new Date(date.getFullYear(), date.getMonth() + months, 1);
const formatDisplayDate$1 = (value, locale) => {
	const date = parseIsoDate$1(value);
	if (!date) return "";
	return new Intl.DateTimeFormat(getLocale$1(locale), {
		day: "2-digit",
		month: "2-digit",
		year: "numeric"
	}).format(date);
};
const formatMonthLabel$1 = (date, locale) => new Intl.DateTimeFormat(getLocale$1(locale), {
	month: "long",
	year: "numeric"
}).format(date);
/**
* Locale-aware first day of week.
* Uses Intl.Locale.weekInfo (Chrome 99+, FF 126+, Safari 17+) with fallback heuristic.
*/
const getFirstDayOfWeek = (locale) => {
	try {
		const info = new Intl.Locale(getLocale$1(locale)).weekInfo;
		if (info && typeof info.firstDay === "number") return info.firstDay === 7 ? 0 : info.firstDay;
	} catch {}
	const country = (getLocale$1(locale).split("-")[1] ?? "").toUpperCase();
	return [
		"US",
		"CA",
		"MX",
		"CN",
		"JP",
		"KR",
		"SA",
		"EG",
		"IL",
		"BR"
	].includes(country) ? 0 : 1;
};
const buildCalendarDays$1 = (month, min, max, locale) => {
	const first = startOfMonth$1(month);
	const firstDayOfWeek = getFirstDayOfWeek(locale);
	const offset = (first.getDay() - firstDayOfWeek + 7) % 7;
	const minDate = parseIsoDate$1(min);
	const maxDate = parseIsoDate$1(max);
	const start = new Date(first);
	start.setDate(first.getDate() - offset);
	return Array.from({ length: 42 }, (_, i) => {
		const date = new Date(start);
		date.setDate(start.getDate() + i);
		return {
			date,
			iso: toIsoDate$1(date),
			inCurrentMonth: date.getMonth() === month.getMonth(),
			disabled: Boolean(minDate && date < minDate || maxDate && date > maxDate)
		};
	});
};
const isIsoDateDisabled = (iso, min, max) => {
	const date = parseIsoDate$1(iso);
	if (!date) return true;
	const minDate = parseIsoDate$1(min);
	const maxDate = parseIsoDate$1(max);
	return Boolean(minDate && date < minDate || maxDate && date > maxDate);
};
const weekDayLabels = (locale) => {
	const firstDayOfWeek = getFirstDayOfWeek(locale);
	const anchor = new Date(2026, 5, 7);
	const formatter = new Intl.DateTimeFormat(getLocale$1(locale), { weekday: "narrow" });
	return Array.from({ length: 7 }, (_, i) => {
		const date = new Date(anchor);
		date.setDate(anchor.getDate() + (firstDayOfWeek + i) % 7);
		return formatter.format(date);
	});
};
/** Short month labels (Jan, Feb, …) — capitalized for display. */
const getMonthLabels = (locale) => {
	const formatter = new Intl.DateTimeFormat(getLocale$1(locale), { month: "short" });
	return Array.from({ length: 12 }, (_, i) => {
		const label = formatter.format(new Date(2024, i, 1));
		return label.charAt(0).toUpperCase() + label.slice(1).replace(".", "");
	});
};
/** Year range: 12-year block containing the given year. */
const getYearRangeStart = (year) => Math.floor(year / 12) * 12;
/**
* Applies dd/mm/yyyy mask.
* Auto-inserts trailing slash when adding the 2nd or 4th digit.
* Does NOT add trailing slash when the user is deleting.
*/
const applyDateMask = (rawValue, prevValue) => {
	const digits = rawValue.replace(/\D/g, "").slice(0, 8);
	const prevDigits = prevValue.replace(/\D/g, "");
	const isAdding = digits.length > prevDigits.length;
	if (digits.length === 0) return "";
	let result = digits.slice(0, 2);
	if (digits.length > 2) result += "/" + digits.slice(2, 4);
	if (digits.length > 4) result += "/" + digits.slice(4, 8);
	if (isAdding && (digits.length === 2 || digits.length === 4)) result += "/";
	return result;
};
/**
* Parses dd/mm/yyyy or ISO yyyy-mm-dd to ISO string.
* Returns null if not a valid date.
*/
const parseDisplayDate = (text, locale) => {
	const match = text.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/);
	if (match) {
		const [, a, b, year] = match;
		const [dd, mm] = (getLocale$1(locale).split("-")[1] ?? "").toUpperCase() === "US" ? [b, a] : [a, b];
		const iso = `${year}-${mm.padStart(2, "0")}-${dd.padStart(2, "0")}`;
		return parseIsoDate$1(iso) ? iso : null;
	}
	if (ISO_DATE_RE$1.test(text)) return parseIsoDate$1(text) ? text : null;
	return null;
};
const DatePicker = forwardRef(function DatePicker({ id, name, value, placeholder = "dd/mm/yyyy", disabled = false, readOnly = false, required = false, invalid = false, clearable = true, locale, min, max, className, ariaLabel, onChange, onBlur }, ref) {
	const generatedId = useId();
	const inputId = id ?? generatedId;
	const selectedDate = parseIsoDate$1(value);
	const resolvedLocale = getLocale$1(locale);
	const displayValue = formatDisplayDate$1(value, resolvedLocale);
	const [open, setOpen] = useState(false);
	const [viewMode, setViewMode] = useState("days");
	const [month, setMonth] = useState(() => startOfMonth$1(selectedDate ?? /* @__PURE__ */ new Date()));
	const [yearRangeStart, setYearRangeStart] = useState(() => getYearRangeStart((/* @__PURE__ */ new Date()).getFullYear()));
	const [panelStyle, setPanelStyle] = useState({});
	const [focusedIso, setFocusedIso] = useState(null);
	const [inputText, setInputText] = useState("");
	const [isEditing, setIsEditing] = useState(false);
	const rootRef = useRef(null);
	const inputRef = useRef(null);
	const triggerRef = useRef(null);
	const panelRef = useRef(null);
	const days = useMemo(() => buildCalendarDays$1(month, min, max, resolvedLocale), [
		max,
		min,
		month,
		resolvedLocale
	]);
	const weekdayLabels = useMemo(() => weekDayLabels(resolvedLocale), [resolvedLocale]);
	const monthLabels = useMemo(() => getMonthLabels(resolvedLocale), [resolvedLocale]);
	const selectedIso = selectedDate ? toIsoDate$1(selectedDate) : "";
	useEffect(() => {
		if (selectedDate) setMonth(startOfMonth$1(selectedDate));
	}, [value]);
	useEffect(() => {
		if (!open) {
			setFocusedIso(null);
			setViewMode("days");
			return;
		}
		const updatePosition = () => {
			const rect = triggerRef.current?.getBoundingClientRect();
			if (!rect) return;
			const panelWidth = panelRef.current?.offsetWidth ?? 296;
			const left = Math.min(Math.max(8, rect.left), window.innerWidth - panelWidth - 8);
			const top = Math.min(rect.bottom + 6, window.innerHeight - 380);
			setPanelStyle({
				left,
				top: Math.max(8, top)
			});
		};
		updatePosition();
		window.addEventListener("resize", updatePosition);
		window.addEventListener("scroll", updatePosition, true);
		return () => {
			window.removeEventListener("resize", updatePosition);
			window.removeEventListener("scroll", updatePosition, true);
		};
	}, [open]);
	useEffect(() => {
		if (!open) return;
		const handlePointerDown = (e) => {
			const target = e.target;
			if (rootRef.current?.contains(target) || panelRef.current?.contains(target)) return;
			setOpen(false);
		};
		const handleKeyDown = (e) => {
			if (e.key === "Escape") {
				e.preventDefault();
				setOpen(false);
				inputRef.current?.focus();
			}
		};
		document.addEventListener("pointerdown", handlePointerDown);
		document.addEventListener("keydown", handleKeyDown);
		return () => {
			document.removeEventListener("pointerdown", handlePointerDown);
			document.removeEventListener("keydown", handleKeyDown);
		};
	}, [open]);
	useEffect(() => {
		if (!open) return;
		const initialIso = selectedDate ? toIsoDate$1(selectedDate) : toIsoDate$1(/* @__PURE__ */ new Date());
		setFocusedIso(initialIso);
		window.setTimeout(() => {
			panelRef.current?.querySelector(`[data-iso="${initialIso}"]`)?.focus();
		}, 0);
	}, [open]);
	const commitValue = useCallback((nextValue) => {
		onChange?.(nextValue);
		setOpen(false);
		setIsEditing(false);
		setInputText("");
		window.setTimeout(() => inputRef.current?.focus());
	}, [onChange]);
	const handleToday = useCallback(() => {
		const today = toIsoDate$1(/* @__PURE__ */ new Date());
		if (isIsoDateDisabled(today, min, max)) return;
		setMonth(startOfMonth$1(parseIsoDate$1(today)));
		commitValue(today);
	}, [
		min,
		max,
		commitValue
	]);
	const handleClear = useCallback((e) => {
		e.preventDefault();
		commitValue("");
	}, [commitValue]);
	const handleInputFocus = () => {
		setIsEditing(true);
		setInputText(displayValue);
		window.setTimeout(() => inputRef.current?.select(), 0);
	};
	const handleInputChange = (e) => {
		if (readOnly || disabled) return;
		setInputText(applyDateMask(e.target.value, inputText));
	};
	const handleInputBlur = (e) => {
		const related = e.relatedTarget;
		if (rootRef.current?.contains(related) || panelRef.current?.contains(related)) return;
		setIsEditing(false);
		const parsed = parseDisplayDate(inputText, resolvedLocale);
		if (parsed !== null) {
			if (parsed !== value) onChange?.(parsed);
		} else if (inputText === "" && value) onChange?.("");
		setInputText("");
		onBlur?.(e);
	};
	const handleInputKeyDown = (e) => {
		if (e.key === "Enter") {
			e.preventDefault();
			const parsed = parseDisplayDate(inputText, resolvedLocale);
			if (parsed !== null) commitValue(parsed);
			else if (inputText === "") commitValue("");
		} else if (e.key === "ArrowDown" || e.key === "F4") {
			e.preventDefault();
			if (!readOnly && !disabled) setOpen(true);
		} else if (e.key === "Escape" && open) setOpen(false);
	};
	const handleCalendarPointerDown = (e) => {
		e.preventDefault();
		if (readOnly || disabled) return;
		setOpen((v) => !v);
	};
	const handleDayKeyDown = useCallback((e, iso) => {
		if (![
			"ArrowLeft",
			"ArrowRight",
			"ArrowUp",
			"ArrowDown",
			"Enter",
			" "
		].includes(e.key)) return;
		e.preventDefault();
		if (e.key === "Enter" || e.key === " ") {
			const day = days.find((d) => d.iso === iso);
			if (day && !day.disabled) commitValue(iso);
			return;
		}
		const delta = {
			ArrowLeft: -1,
			ArrowRight: 1,
			ArrowUp: -7,
			ArrowDown: 7
		}[e.key] ?? 0;
		const current = parseIsoDate$1(iso);
		if (!current) return;
		const next = new Date(current);
		next.setDate(current.getDate() + delta);
		const nextIso = toIsoDate$1(next);
		if (next.getMonth() !== month.getMonth() || next.getFullYear() !== month.getFullYear()) setMonth(startOfMonth$1(next));
		setFocusedIso(nextIso);
		window.setTimeout(() => {
			panelRef.current?.querySelector(`[data-iso="${nextIso}"]`)?.focus();
		}, 0);
	}, [
		days,
		month,
		commitValue
	]);
	const handleMonthSelect = (monthIndex) => {
		setMonth(new Date(month.getFullYear(), monthIndex, 1));
		setViewMode("days");
	};
	const handleYearSelect = (year) => {
		setMonth(new Date(year, month.getMonth(), 1));
		setViewMode("months");
	};
	const setInputRef = (node) => {
		inputRef.current = node;
		if (typeof ref === "function") ref(node);
		else if (ref) ref.current = node;
	};
	const todayIso = toIsoDate$1(/* @__PURE__ */ new Date());
	return /* @__PURE__ */ jsxs("div", {
		ref: rootRef,
		className: cx$1("app-date-picker", className),
		children: [
			/* @__PURE__ */ jsx("input", {
				type: "hidden",
				name,
				value: value ?? "",
				required
			}),
			/* @__PURE__ */ jsxs("div", {
				ref: triggerRef,
				className: cx$1("app-date-picker__trigger", invalid && "app-date-picker__trigger--invalid", readOnly && "app-date-picker__trigger--readonly", open && "app-date-picker__trigger--open", disabled && "app-date-picker__trigger--disabled"),
				children: [/* @__PURE__ */ jsx("input", {
					ref: setInputRef,
					id: inputId,
					type: "text",
					inputMode: "numeric",
					className: cx$1("app-date-picker__input", !isEditing && !displayValue && "app-date-picker__input--placeholder"),
					value: isEditing ? inputText : displayValue,
					placeholder,
					disabled,
					readOnly,
					"aria-label": ariaLabel,
					"aria-haspopup": "dialog",
					"aria-expanded": open,
					"aria-invalid": invalid || void 0,
					"aria-required": required || void 0,
					autoComplete: "off",
					onFocus: handleInputFocus,
					onBlur: handleInputBlur,
					onChange: handleInputChange,
					onKeyDown: handleInputKeyDown
				}), /* @__PURE__ */ jsxs("span", {
					className: "app-date-picker__icons",
					children: [clearable && displayValue && !disabled && !readOnly && /* @__PURE__ */ jsx("span", {
						role: "button",
						tabIndex: -1,
						className: "app-date-picker__clear",
						"aria-label": "Limpiar fecha",
						onPointerDown: handleClear,
						children: /* @__PURE__ */ jsx("i", {
							className: "ph ph-x",
							"aria-hidden": "true"
						})
					}), /* @__PURE__ */ jsx("span", {
						role: "button",
						tabIndex: -1,
						className: "app-date-picker__calendar-btn",
						"aria-label": "Abrir calendario",
						onPointerDown: handleCalendarPointerDown,
						children: /* @__PURE__ */ jsx("i", {
							className: "ph ph-calendar-blank",
							"aria-hidden": "true"
						})
					})]
				})]
			}),
			open && createPortal(/* @__PURE__ */ jsxs("div", {
				ref: panelRef,
				className: "app-date-picker__panel",
				role: "dialog",
				"aria-label": "Seleccionar fecha",
				style: panelStyle,
				children: [
					viewMode === "days" && /* @__PURE__ */ jsxs(Fragment, { children: [
						/* @__PURE__ */ jsxs("div", {
							className: "app-date-picker__header",
							children: [
								/* @__PURE__ */ jsx("button", {
									type: "button",
									className: "app-date-picker__nav",
									"aria-label": "Mes anterior",
									onClick: () => setMonth((m) => addMonths$1(m, -1)),
									children: /* @__PURE__ */ jsx("i", {
										className: "ph ph-caret-left",
										"aria-hidden": "true"
									})
								}),
								/* @__PURE__ */ jsxs("button", {
									type: "button",
									className: "app-date-picker__month app-date-picker__month--clickable",
									"aria-label": "Seleccionar mes y año",
									onClick: () => setViewMode("months"),
									children: [formatMonthLabel$1(month, resolvedLocale), /* @__PURE__ */ jsx("i", {
										className: "ph ph-caret-down app-date-picker__month-caret",
										"aria-hidden": "true"
									})]
								}),
								/* @__PURE__ */ jsx("button", {
									type: "button",
									className: "app-date-picker__nav",
									"aria-label": "Mes siguiente",
									onClick: () => setMonth((m) => addMonths$1(m, 1)),
									children: /* @__PURE__ */ jsx("i", {
										className: "ph ph-caret-right",
										"aria-hidden": "true"
									})
								})
							]
						}),
						/* @__PURE__ */ jsx("div", {
							className: "app-date-picker__weekdays",
							"aria-hidden": "true",
							children: weekdayLabels.map((label, i) => /* @__PURE__ */ jsx("span", { children: label }, `${label}-${i}`))
						}),
						/* @__PURE__ */ jsx("div", {
							className: "app-date-picker__days",
							role: "grid",
							"aria-label": formatMonthLabel$1(month, resolvedLocale),
							children: days.map((day) => /* @__PURE__ */ jsx("button", {
								"data-iso": day.iso,
								type: "button",
								className: cx$1("app-date-picker__day", !day.inCurrentMonth && "app-date-picker__day--muted", day.iso === selectedIso && "app-date-picker__day--selected", day.iso === todayIso && "app-date-picker__day--today"),
								disabled: day.disabled,
								role: "gridcell",
								"aria-selected": day.iso === selectedIso,
								tabIndex: day.iso === (focusedIso ?? selectedIso ?? todayIso) ? 0 : -1,
								onClick: () => commitValue(day.iso),
								onKeyDown: (e) => handleDayKeyDown(e, day.iso),
								children: day.date.getDate()
							}, day.iso))
						}),
						/* @__PURE__ */ jsxs("div", {
							className: "app-date-picker__footer",
							children: [/* @__PURE__ */ jsx("button", {
								type: "button",
								className: "app-date-picker__text-button",
								onClick: () => commitValue(""),
								children: "Limpiar"
							}), /* @__PURE__ */ jsx("button", {
								type: "button",
								className: "app-date-picker__text-button",
								onClick: handleToday,
								children: "Hoy"
							})]
						})
					] }),
					viewMode === "months" && /* @__PURE__ */ jsxs(Fragment, { children: [
						/* @__PURE__ */ jsxs("div", {
							className: "app-date-picker__header",
							children: [
								/* @__PURE__ */ jsx("button", {
									type: "button",
									className: "app-date-picker__nav",
									"aria-label": "Año anterior",
									onClick: () => setMonth((m) => new Date(m.getFullYear() - 1, m.getMonth(), 1)),
									children: /* @__PURE__ */ jsx("i", {
										className: "ph ph-caret-left",
										"aria-hidden": "true"
									})
								}),
								/* @__PURE__ */ jsxs("button", {
									type: "button",
									className: "app-date-picker__month app-date-picker__month--clickable",
									"aria-label": "Seleccionar año",
									onClick: () => {
										setYearRangeStart(getYearRangeStart(month.getFullYear()));
										setViewMode("years");
									},
									children: [month.getFullYear(), /* @__PURE__ */ jsx("i", {
										className: "ph ph-caret-down app-date-picker__month-caret",
										"aria-hidden": "true"
									})]
								}),
								/* @__PURE__ */ jsx("button", {
									type: "button",
									className: "app-date-picker__nav",
									"aria-label": "Año siguiente",
									onClick: () => setMonth((m) => new Date(m.getFullYear() + 1, m.getMonth(), 1)),
									children: /* @__PURE__ */ jsx("i", {
										className: "ph ph-caret-right",
										"aria-hidden": "true"
									})
								})
							]
						}),
						/* @__PURE__ */ jsx("div", {
							className: "app-date-picker__months-grid",
							role: "grid",
							"aria-label": `${month.getFullYear()}`,
							children: monthLabels.map((label, i) => {
								const isSelected = selectedDate ? selectedDate.getMonth() === i && selectedDate.getFullYear() === month.getFullYear() : false;
								const isCurrent = (/* @__PURE__ */ new Date()).getMonth() === i && (/* @__PURE__ */ new Date()).getFullYear() === month.getFullYear();
								return /* @__PURE__ */ jsx("button", {
									type: "button",
									className: cx$1("app-date-picker__month-cell", isSelected && "app-date-picker__month-cell--selected", isCurrent && "app-date-picker__month-cell--current"),
									role: "gridcell",
									"aria-selected": isSelected,
									onClick: () => handleMonthSelect(i),
									children: label
								}, i);
							})
						}),
						/* @__PURE__ */ jsx("div", {
							className: "app-date-picker__footer",
							children: /* @__PURE__ */ jsxs("button", {
								type: "button",
								className: "app-date-picker__text-button",
								onClick: () => setViewMode("days"),
								children: [/* @__PURE__ */ jsx("i", {
									className: "ph ph-arrow-left",
									"aria-hidden": "true"
								}), " Volver"]
							})
						})
					] }),
					viewMode === "years" && /* @__PURE__ */ jsxs(Fragment, { children: [
						/* @__PURE__ */ jsxs("div", {
							className: "app-date-picker__header",
							children: [
								/* @__PURE__ */ jsx("button", {
									type: "button",
									className: "app-date-picker__nav",
									"aria-label": "Rango anterior",
									onClick: () => setYearRangeStart((y) => y - 12),
									children: /* @__PURE__ */ jsx("i", {
										className: "ph ph-caret-left",
										"aria-hidden": "true"
									})
								}),
								/* @__PURE__ */ jsxs("div", {
									className: "app-date-picker__month",
									children: [
										yearRangeStart,
										"–",
										yearRangeStart + 11
									]
								}),
								/* @__PURE__ */ jsx("button", {
									type: "button",
									className: "app-date-picker__nav",
									"aria-label": "Rango siguiente",
									onClick: () => setYearRangeStart((y) => y + 12),
									children: /* @__PURE__ */ jsx("i", {
										className: "ph ph-caret-right",
										"aria-hidden": "true"
									})
								})
							]
						}),
						/* @__PURE__ */ jsx("div", {
							className: "app-date-picker__years-grid",
							role: "grid",
							"aria-label": `${yearRangeStart}–${yearRangeStart + 11}`,
							children: Array.from({ length: 12 }, (_, i) => yearRangeStart + i).map((year) => {
								const isSelected = selectedDate ? selectedDate.getFullYear() === year : false;
								const isCurrent = (/* @__PURE__ */ new Date()).getFullYear() === year;
								return /* @__PURE__ */ jsx("button", {
									type: "button",
									className: cx$1("app-date-picker__year-cell", isSelected && "app-date-picker__year-cell--selected", isCurrent && "app-date-picker__year-cell--current"),
									role: "gridcell",
									"aria-selected": isSelected,
									onClick: () => handleYearSelect(year),
									children: year
								}, year);
							})
						}),
						/* @__PURE__ */ jsx("div", {
							className: "app-date-picker__footer",
							children: /* @__PURE__ */ jsxs("button", {
								type: "button",
								className: "app-date-picker__text-button",
								onClick: () => setViewMode("months"),
								children: [/* @__PURE__ */ jsx("i", {
									className: "ph ph-arrow-left",
									"aria-hidden": "true"
								}), " Volver"]
							})
						})
					] })
				]
			}), document.body)
		]
	});
});
//#endregion
//#region assets/ui/DateRangePicker.tsx
/**
* DateRangePicker — enterprise date range selector.
*
* A single trigger with two text inputs (start + end) sharing one calendar
* panel. Supports hover-preview, range highlighting, keyboard entry, min/max
* constraints, and full density/theme token compliance.
*
* Usage:
*   <DateRangePicker
*     startValue="2026-06-01"
*     endValue="2026-06-30"
*     onChange={(start, end) => ...}
*   />
*/
const ISO_DATE_RE = /^\d{4}-\d{2}-\d{2}$/;
const pad = (v) => String(v).padStart(2, "0");
const toIsoDate = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const parseIsoDate = (value) => {
	if (!value || !ISO_DATE_RE.test(value)) return null;
	const [year, month, day] = value.split("-").map(Number);
	const d = new Date(year, month - 1, day);
	if (d.getFullYear() !== year || d.getMonth() !== month - 1 || d.getDate() !== day) return null;
	return d;
};
const cx = (...vals) => vals.filter(Boolean).join(" ");
const getLocale = (override) => override || document.documentElement.lang || navigator.language || "en-US";
const startOfMonth = (date) => new Date(date.getFullYear(), date.getMonth(), 1);
const addMonths = (date, months) => new Date(date.getFullYear(), date.getMonth() + months, 1);
const formatDisplayDate = (value, locale) => {
	const date = parseIsoDate(value);
	if (!date) return "";
	return new Intl.DateTimeFormat(getLocale(locale), {
		day: "2-digit",
		month: "2-digit",
		year: "numeric"
	}).format(date);
};
const formatMonthLabel = (date, locale) => new Intl.DateTimeFormat(getLocale(locale), {
	month: "long",
	year: "numeric"
}).format(date);
const buildCalendarDays = (month, min, max, locale) => {
	const first = startOfMonth(month);
	const firstDayOfWeek = getFirstDayOfWeek(locale);
	const offset = (first.getDay() - firstDayOfWeek + 7) % 7;
	const minDate = parseIsoDate(min);
	const maxDate = parseIsoDate(max);
	const start = new Date(first);
	start.setDate(first.getDate() - offset);
	return Array.from({ length: 42 }, (_, i) => {
		const date = new Date(start);
		date.setDate(start.getDate() + i);
		return {
			date,
			iso: toIsoDate(date),
			inCurrentMonth: date.getMonth() === month.getMonth(),
			disabled: Boolean(minDate && date < minDate || maxDate && date > maxDate)
		};
	});
};
const getDayRoleInRange = (iso, startIso, endIso) => {
	if (!startIso) return "none";
	if (!endIso || startIso === endIso) return iso === startIso ? "single" : "none";
	if (iso === startIso) return "start";
	if (iso === endIso) return "end";
	if (iso > startIso && iso < endIso) return "in-range";
	return "none";
};
function DateRangePicker({ startValue, endValue, placeholder = "dd/mm/yyyy", disabled = false, readOnly = false, required = false, invalid = false, locale, min, max, className, ariaLabel, onChange, onBlur }) {
	const startId = useId();
	const endId = useId();
	const resolvedLocale = getLocale(locale);
	const startDisplay = formatDisplayDate(startValue, resolvedLocale);
	const endDisplay = formatDisplayDate(endValue, resolvedLocale);
	const startIso = parseIsoDate(startValue) ? startValue : null;
	const endIso = parseIsoDate(endValue) ? endValue : null;
	const [open, setOpen] = useState(false);
	const [phase, setPhase] = useState("idle");
	const [pendingStart, setPendingStart] = useState(null);
	const [hoverIso, setHoverIso] = useState(null);
	const [month, setMonth] = useState(() => startOfMonth(parseIsoDate(startValue) ?? /* @__PURE__ */ new Date()));
	const [panelStyle, setPanelStyle] = useState({});
	const [startText, setStartText] = useState("");
	const [endText, setEndText] = useState("");
	const [editingField, setEditingField] = useState(null);
	const rootRef = useRef(null);
	const triggerRef = useRef(null);
	const panelRef = useRef(null);
	const startInputRef = useRef(null);
	const endInputRef = useRef(null);
	const days = useMemo(() => buildCalendarDays(month, min, max, resolvedLocale), [
		month,
		min,
		max,
		resolvedLocale
	]);
	const weekdayLbls = useMemo(() => weekDayLabels(resolvedLocale), [resolvedLocale]);
	const todayIso = toIsoDate(/* @__PURE__ */ new Date());
	useEffect(() => {
		const anchor = parseIsoDate(startValue) ?? parseIsoDate(endValue);
		if (anchor) setMonth(startOfMonth(anchor));
	}, [startValue]);
	useEffect(() => {
		if (!open) return;
		const update = () => {
			const rect = triggerRef.current?.getBoundingClientRect();
			if (!rect) return;
			const panelWidth = panelRef.current?.offsetWidth ?? 296;
			const left = Math.min(Math.max(8, rect.left), window.innerWidth - panelWidth - 8);
			const top = Math.min(rect.bottom + 6, window.innerHeight - 380);
			setPanelStyle({
				left,
				top: Math.max(8, top)
			});
		};
		update();
		window.addEventListener("resize", update);
		window.addEventListener("scroll", update, true);
		return () => {
			window.removeEventListener("resize", update);
			window.removeEventListener("scroll", update, true);
		};
	}, [open]);
	useEffect(() => {
		if (!open) return;
		const handlePointerDown = (e) => {
			const target = e.target;
			if (rootRef.current?.contains(target) || panelRef.current?.contains(target)) return;
			closePanel();
		};
		const handleKeyDown = (e) => {
			if (e.key === "Escape") {
				e.preventDefault();
				closePanel();
				startInputRef.current?.focus();
			}
		};
		document.addEventListener("pointerdown", handlePointerDown);
		document.addEventListener("keydown", handleKeyDown);
		return () => {
			document.removeEventListener("pointerdown", handlePointerDown);
			document.removeEventListener("keydown", handleKeyDown);
		};
	}, [open]);
	const closePanel = useCallback(() => {
		setOpen(false);
		setPhase("idle");
		setPendingStart(null);
		setHoverIso(null);
	}, []);
	const commitRange = useCallback((start, end) => {
		const [a, b] = start <= end ? [start, end] : [end, start];
		onChange?.(a, b);
		closePanel();
		window.setTimeout(() => startInputRef.current?.focus());
	}, [onChange, closePanel]);
	const handleDayClick = useCallback((iso) => {
		if (phase === "idle") {
			setPendingStart(iso);
			setPhase("selecting-end");
		} else commitRange(pendingStart ?? iso, iso);
	}, [
		phase,
		pendingStart,
		commitRange
	]);
	const getEffectiveRange = () => {
		if (phase === "selecting-end" && pendingStart) {
			const effectiveEnd = hoverIso ?? null;
			return {
				effectiveStart: pendingStart <= (effectiveEnd ?? pendingStart) ? pendingStart : effectiveEnd,
				effectiveEnd: pendingStart <= (effectiveEnd ?? pendingStart) ? effectiveEnd : pendingStart
			};
		}
		return {
			effectiveStart: startIso,
			effectiveEnd: endIso
		};
	};
	const { effectiveStart, effectiveEnd } = getEffectiveRange();
	const handleStartFocus = () => {
		setEditingField("start");
		setStartText(startDisplay);
		window.setTimeout(() => startInputRef.current?.select(), 0);
	};
	const handleEndFocus = () => {
		setEditingField("end");
		setEndText(endDisplay);
		window.setTimeout(() => endInputRef.current?.select(), 0);
	};
	const handleStartChange = (e) => {
		if (readOnly || disabled) return;
		setStartText(applyDateMask(e.target.value, startText));
	};
	const handleEndChange = (e) => {
		if (readOnly || disabled) return;
		setEndText(applyDateMask(e.target.value, endText));
	};
	const commitTextInput = (field, text) => {
		const parsed = parseDisplayDate(text, resolvedLocale);
		if (parsed !== null) {
			const newStart = field === "start" ? parsed : startIso ?? "";
			const newEnd = field === "end" ? parsed : endIso ?? "";
			if (newStart && newEnd) {
				const [a, b] = newStart <= newEnd ? [newStart, newEnd] : [newEnd, newStart];
				onChange?.(a, b);
			} else if (field === "start") onChange?.(parsed, endIso ?? "");
			else onChange?.(startIso ?? "", parsed);
		} else if (text === "") if (field === "start") onChange?.("", endIso ?? "");
		else onChange?.(startIso ?? "", "");
	};
	const handleStartBlur = (e) => {
		const related = e.relatedTarget;
		if (rootRef.current?.contains(related) || panelRef.current?.contains(related)) return;
		commitTextInput("start", startText);
		setEditingField(null);
		setStartText("");
		onBlur?.(e);
	};
	const handleEndBlur = (e) => {
		const related = e.relatedTarget;
		if (rootRef.current?.contains(related) || panelRef.current?.contains(related)) return;
		commitTextInput("end", endText);
		setEditingField(null);
		setEndText("");
		onBlur?.(e);
	};
	const handleStartKeyDown = (e) => {
		if (e.key === "Enter") {
			e.preventDefault();
			const parsed = parseDisplayDate(startText);
			if (parsed) onChange?.(parsed, endIso ?? "");
			endInputRef.current?.focus();
		} else if (e.key === "ArrowDown" || e.key === "F4") {
			e.preventDefault();
			if (!readOnly && !disabled) setOpen(true);
		}
	};
	const handleEndKeyDown = (e) => {
		if (e.key === "Enter") {
			e.preventDefault();
			const parsed = parseDisplayDate(endText);
			if (parsed) onChange?.(startIso ?? "", parsed);
		} else if (e.key === "ArrowDown" || e.key === "F4") {
			e.preventDefault();
			if (!readOnly && !disabled) setOpen(true);
		}
	};
	const handleCalendarPointerDown = (e) => {
		e.preventDefault();
		if (readOnly || disabled) return;
		setOpen((v) => !v);
	};
	const handleClear = (e) => {
		e.preventDefault();
		onChange?.("", "");
		closePanel();
	};
	return /* @__PURE__ */ jsxs("div", {
		ref: rootRef,
		className: cx("app-date-range-picker", className),
		children: [
			/* @__PURE__ */ jsxs("div", {
				ref: triggerRef,
				className: cx("app-date-range-picker__trigger", invalid && "app-date-range-picker__trigger--invalid", readOnly && "app-date-range-picker__trigger--readonly", open && "app-date-range-picker__trigger--open", disabled && "app-date-range-picker__trigger--disabled"),
				"aria-label": ariaLabel,
				children: [
					/* @__PURE__ */ jsx("input", {
						ref: startInputRef,
						id: startId,
						type: "text",
						inputMode: "numeric",
						className: cx("app-date-range-picker__input", !editingField && !startDisplay && "app-date-range-picker__input--placeholder"),
						value: editingField === "start" ? startText : startDisplay,
						placeholder,
						disabled,
						readOnly,
						autoComplete: "off",
						"aria-label": `Fecha inicio${ariaLabel ? ` ${ariaLabel}` : ""}`,
						"aria-required": required || void 0,
						onFocus: handleStartFocus,
						onBlur: handleStartBlur,
						onChange: handleStartChange,
						onKeyDown: handleStartKeyDown
					}),
					/* @__PURE__ */ jsx("span", {
						className: "app-date-range-picker__sep",
						"aria-hidden": "true",
						children: /* @__PURE__ */ jsx("i", { className: "ph ph-arrow-right" })
					}),
					/* @__PURE__ */ jsx("input", {
						ref: endInputRef,
						id: endId,
						type: "text",
						inputMode: "numeric",
						className: cx("app-date-range-picker__input", !editingField && !endDisplay && "app-date-range-picker__input--placeholder"),
						value: editingField === "end" ? endText : endDisplay,
						placeholder,
						disabled,
						readOnly,
						autoComplete: "off",
						"aria-label": `Fecha fin${ariaLabel ? ` ${ariaLabel}` : ""}`,
						onFocus: handleEndFocus,
						onBlur: handleEndBlur,
						onChange: handleEndChange,
						onKeyDown: handleEndKeyDown
					}),
					/* @__PURE__ */ jsxs("span", {
						className: "app-date-range-picker__icons",
						children: [(startDisplay || endDisplay) && !disabled && !readOnly && /* @__PURE__ */ jsx("span", {
							role: "button",
							tabIndex: -1,
							className: "app-date-range-picker__clear",
							"aria-label": "Limpiar rango",
							onPointerDown: handleClear,
							children: /* @__PURE__ */ jsx("i", {
								className: "ph ph-x",
								"aria-hidden": "true"
							})
						}), /* @__PURE__ */ jsx("span", {
							role: "button",
							tabIndex: -1,
							className: "app-date-range-picker__calendar-btn",
							"aria-label": "Abrir calendario",
							onPointerDown: handleCalendarPointerDown,
							children: /* @__PURE__ */ jsx("i", {
								className: "ph ph-calendar-blank",
								"aria-hidden": "true"
							})
						})]
					})
				]
			}),
			phase === "selecting-end" && /* @__PURE__ */ jsx("div", {
				className: "app-date-range-picker__hint",
				role: "status",
				"aria-live": "polite",
				children: "Selecciona la fecha de fin"
			}),
			open && createPortal(/* @__PURE__ */ jsxs("div", {
				ref: panelRef,
				className: "app-date-range-picker__panel",
				role: "dialog",
				"aria-label": "Seleccionar rango de fechas",
				style: panelStyle,
				children: [
					/* @__PURE__ */ jsxs("div", {
						className: "app-date-range-picker__header",
						children: [
							/* @__PURE__ */ jsx("button", {
								type: "button",
								className: "app-date-range-picker__nav",
								"aria-label": "Mes anterior",
								onClick: () => setMonth((m) => addMonths(m, -1)),
								children: /* @__PURE__ */ jsx("i", {
									className: "ph ph-caret-left",
									"aria-hidden": "true"
								})
							}),
							/* @__PURE__ */ jsx("div", {
								className: "app-date-range-picker__month",
								"aria-live": "polite",
								children: formatMonthLabel(month, resolvedLocale)
							}),
							/* @__PURE__ */ jsx("button", {
								type: "button",
								className: "app-date-range-picker__nav",
								"aria-label": "Mes siguiente",
								onClick: () => setMonth((m) => addMonths(m, 1)),
								children: /* @__PURE__ */ jsx("i", {
									className: "ph ph-caret-right",
									"aria-hidden": "true"
								})
							})
						]
					}),
					/* @__PURE__ */ jsx("div", {
						className: "app-date-range-picker__weekdays",
						"aria-hidden": "true",
						children: weekdayLbls.map((label, i) => /* @__PURE__ */ jsx("span", { children: label }, `${label}-${i}`))
					}),
					phase === "selecting-end" && /* @__PURE__ */ jsxs("div", {
						className: "app-date-range-picker__phase-banner",
						children: [/* @__PURE__ */ jsx("i", {
							className: "ph ph-info",
							"aria-hidden": "true"
						}), "Ahora selecciona la fecha de fin"]
					}),
					/* @__PURE__ */ jsx("div", {
						className: "app-date-range-picker__days",
						role: "grid",
						"aria-label": formatMonthLabel(month, resolvedLocale),
						children: days.map((day) => {
							const role = getDayRoleInRange(day.iso, effectiveStart, effectiveEnd);
							return /* @__PURE__ */ jsx("button", {
								type: "button",
								className: cx("app-date-range-picker__day", !day.inCurrentMonth && "app-date-range-picker__day--muted", day.iso === todayIso && "app-date-range-picker__day--today", role === "start" && "app-date-range-picker__day--range-start", role === "end" && "app-date-range-picker__day--range-end", role === "in-range" && "app-date-range-picker__day--in-range", role === "single" && "app-date-range-picker__day--range-single", phase === "selecting-end" && "app-date-range-picker__day--selecting"),
								disabled: day.disabled,
								role: "gridcell",
								"aria-selected": role !== "none",
								onMouseEnter: () => phase === "selecting-end" && setHoverIso(day.iso),
								onMouseLeave: () => phase === "selecting-end" && setHoverIso(null),
								onClick: () => !day.disabled && handleDayClick(day.iso),
								children: /* @__PURE__ */ jsx("span", {
									className: "app-date-range-picker__day-inner",
									children: day.date.getDate()
								})
							}, day.iso);
						})
					}),
					/* @__PURE__ */ jsxs("div", {
						className: "app-date-range-picker__footer",
						children: [/* @__PURE__ */ jsx("button", {
							type: "button",
							className: "app-date-range-picker__text-button",
							onClick: () => {
								onChange?.("", "");
								closePanel();
							},
							children: "Limpiar"
						}), phase === "selecting-end" ? /* @__PURE__ */ jsx("button", {
							type: "button",
							className: "app-date-range-picker__text-button",
							onClick: () => {
								setPendingStart(null);
								setPhase("idle");
								setHoverIso(null);
							},
							children: "Cancelar"
						}) : /* @__PURE__ */ jsx("button", {
							type: "button",
							className: "app-date-range-picker__text-button",
							onClick: () => {
								const today = toIsoDate(/* @__PURE__ */ new Date());
								setMonth(startOfMonth(/* @__PURE__ */ new Date()));
								if (!startIso) onChange?.(today, endIso ?? "");
								else if (!endIso) onChange?.(startIso, today);
								else onChange?.(today, today);
								closePanel();
							},
							children: "Hoy"
						})]
					})
				]
			}), document.body)
		]
	});
}
//#endregion
//#region assets/ui/useFloatingPanel.ts
function useFloatingPanel(options = {}) {
	const [open, setOpenState] = useState(false);
	const containerRef = useRef(null);
	const onCloseRef = useRef(options.onClose);
	onCloseRef.current = options.onClose;
	const setOpen = useCallback((next) => {
		setOpenState(next);
		if (!next) onCloseRef.current?.();
	}, []);
	const toggle = useCallback(() => setOpen(!open), [open, setOpen]);
	useEffect(() => {
		if (!open) return;
		const handlePointerDown = (e) => {
			const container = containerRef.current;
			if (container && !container.contains(e.target)) setOpen(false);
		};
		const handleKeyDown = (e) => {
			if (e.key === "Escape") setOpen(false);
		};
		document.addEventListener("pointerdown", handlePointerDown);
		document.addEventListener("keydown", handleKeyDown);
		return () => {
			document.removeEventListener("pointerdown", handlePointerDown);
			document.removeEventListener("keydown", handleKeyDown);
		};
	}, [open, setOpen]);
	return {
		open,
		setOpen,
		toggle,
		containerRef
	};
}
//#endregion
//#region assets/ui/Popover.tsx
const Popover = ({ trigger, panel, align = "end", ariaLabel, className }) => {
	const { open, setOpen, toggle, containerRef } = useFloatingPanel();
	return /* @__PURE__ */ jsxs("div", {
		ref: containerRef,
		className: [
			"app-popover",
			`app-popover--${align}`,
			open && "app-popover--open",
			className
		].filter(Boolean).join(" "),
		children: [trigger({
			open,
			toggle
		}), open && /* @__PURE__ */ jsx("div", {
			className: "app-popover__panel",
			role: "dialog",
			"aria-label": ariaLabel,
			children: panel({ close: () => setOpen(false) })
		})]
	});
};
//#endregion
export { ACCENT_PRESETS, AppDialog, AppToolbar, Avatar, Badge, Button, Card, Chip, CollapsibleSection, ConfirmDialog, ContextMenu, DatePicker, DateRangePicker, DensityContext, DensityProvider, EmptyState, FormField, IconButton, Popover, SelectField, SettingsPanel, Skeleton, StatCard, TextAreaField, TextField, ThemeContext, ThemeProvider, ThemeSwitcher, Toggle, getAvatarHue, getAvatarInitials, useAccentColor, useDensity, useFloatingPanel, useTheme };
