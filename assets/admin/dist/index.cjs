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
let react_router_dom = require("react-router-dom");
let _nubit_ui = require("@nubit/ui");
let react_jsx_runtime = require("react/jsx-runtime");
//#region assets/admin/AdminHeader.tsx
function ActionPopover({ action }) {
	const { open, toggle, setOpen, containerRef } = (0, _nubit_ui.useFloatingPanel)();
	if (action.onClick) return /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", {
		className: "nb-admin-messages",
		children: [/* @__PURE__ */ (0, react_jsx_runtime.jsx)(_nubit_ui.IconButton, {
			icon: action.icon,
			label: action.label,
			onClick: action.onClick
		}), !!action.badge && action.badge > 0 && /* @__PURE__ */ (0, react_jsx_runtime.jsx)(_nubit_ui.Badge, {
			variant: "danger",
			size: "sm",
			pill: true,
			"aria-label": `${action.badge} ${action.label}`,
			children: action.badge > 99 ? "99+" : action.badge
		})]
	});
	return /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", {
		className: "nb-admin-header-popover",
		ref: containerRef,
		children: [/* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", {
			className: "nb-admin-messages",
			children: [/* @__PURE__ */ (0, react_jsx_runtime.jsx)(_nubit_ui.IconButton, {
				icon: action.icon,
				label: action.label,
				"aria-expanded": open,
				onClick: toggle
			}), !!action.badge && action.badge > 0 && /* @__PURE__ */ (0, react_jsx_runtime.jsx)(_nubit_ui.Badge, {
				variant: "danger",
				size: "sm",
				pill: true,
				"aria-label": `${action.badge} ${action.label}`,
				children: action.badge > 99 ? "99+" : action.badge
			})]
		}), open && action.renderPanel && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("div", {
			className: "nb-admin-header-popover__panel",
			role: "dialog",
			"aria-label": action.label,
			children: action.renderPanel({ close: () => setOpen(false) })
		})]
	});
}
function UserMenuPopover({ renderUserMenu }) {
	const { open, toggle, setOpen, containerRef } = (0, _nubit_ui.useFloatingPanel)();
	return /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", {
		className: "nb-admin-header-popover",
		ref: containerRef,
		children: [/* @__PURE__ */ (0, react_jsx_runtime.jsx)(_nubit_ui.IconButton, {
			icon: "ph ph-user-circle",
			label: "User menu",
			"aria-expanded": open,
			onClick: toggle
		}), open && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("div", {
			className: "nb-admin-header-popover__panel",
			role: "dialog",
			"aria-label": "User menu",
			children: renderUserMenu({ close: () => setOpen(false) })
		})]
	});
}
const AdminHeader = ({ title, menuToggleEnabled, toggleMenu, className, actions = [], renderUserMenu, renderThemeSwitcher }) => /* @__PURE__ */ (0, react_jsx_runtime.jsx)("header", {
	className: ["nb-admin-header-component", className].filter(Boolean).join(" "),
	children: /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", {
		className: "nb-admin-header-toolbar",
		role: "toolbar",
		"aria-label": "Main toolbar",
		children: [/* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", {
			className: "nb-admin-header-toolbar__before",
			children: [menuToggleEnabled && /* @__PURE__ */ (0, react_jsx_runtime.jsx)(_nubit_ui.IconButton, {
				className: "nb-admin-menu-button",
				icon: "ph ph-list",
				label: "Toggle menu",
				onClick: toggleMenu
			}), title && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("div", {
				className: "nb-admin-header-title",
				children: title
			})]
		}), /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", {
			className: "nb-admin-header-toolbar__after",
			children: [
				renderThemeSwitcher?.(),
				actions.map((action) => /* @__PURE__ */ (0, react_jsx_runtime.jsx)(ActionPopover, { action }, action.id)),
				renderUserMenu && /* @__PURE__ */ (0, react_jsx_runtime.jsx)(UserMenuPopover, { renderUserMenu })
			]
		})]
	})
});
//#endregion
//#region assets/admin/useScreenSize.ts
let handlers = [];
const xSmallMedia = window.matchMedia("(max-width: 575.98px)");
const smallMedia = window.matchMedia("(min-width: 576px) and (max-width: 991.98px)");
const mediumMedia = window.matchMedia("(min-width: 992px) and (max-width: 1199.98px)");
const largeMedia = window.matchMedia("(min-width: 1200px)");
let debounceTimer = null;
const notifyHandlers = () => {
	if (debounceTimer !== null) clearTimeout(debounceTimer);
	debounceTimer = setTimeout(() => {
		handlers.forEach((handler) => handler());
	}, 50);
};
[
	xSmallMedia,
	smallMedia,
	mediumMedia,
	largeMedia
].forEach((media) => {
	media.addEventListener("change", (e) => {
		if (e.matches) notifyHandlers();
	});
});
const subscribe = (handler) => handlers.push(handler);
const unsubscribe = (handler) => {
	handlers = handlers.filter((item) => item !== handler);
};
function getScreenSize() {
	return {
		isXSmall: xSmallMedia.matches,
		isSmall: smallMedia.matches,
		isMedium: mediumMedia.matches,
		isLarge: largeMedia.matches
	};
}
const useScreenSize = () => {
	const [screenSize, setScreenSize] = (0, react.useState)(getScreenSize());
	const onSizeChanged = (0, react.useCallback)(() => setScreenSize(getScreenSize()), []);
	(0, react.useEffect)(() => {
		subscribe(onSizeChanged);
		return () => unsubscribe(onSizeChanged);
	}, [onSizeChanged]);
	return screenSize;
};
const useScreenSizeClass = () => {
	const { isLarge, isMedium, isSmall } = useScreenSize();
	if (isLarge) return "screen-large";
	if (isMedium) return "screen-medium";
	if (isSmall) return "screen-small";
	return "screen-x-small";
};
//#endregion
//#region assets/admin/AdminSidebarMenu.tsx
const normalizeRoute = (path) => {
	if (!path) return "";
	const normalized = path.startsWith("/") ? path : `/${path}`;
	return normalized.length > 1 ? normalized.replace(/\/+$/, "") : normalized;
};
const getIconClassName = (icon) => {
	if (!icon) return "";
	return icon.includes(" ") ? icon : `ph ph-${icon}`;
};
const AdminSidebarMenu = ({ items, compactMode = false, selectedItemChanged, openMenu, footer }) => {
	const [expandedItemKeys, setExpandedItemKeys] = (0, react.useState)(/* @__PURE__ */ new Set());
	const { isLarge } = useScreenSize();
	const location = (0, react_router_dom.useLocation)();
	(0, react.useEffect)(() => {
		setExpandedItemKeys(/* @__PURE__ */ new Set());
	}, [isLarge, items]);
	const activePath = (0, react.useMemo)(() => {
		const flattenPaths = (menuItems) => menuItems.flatMap((item) => [normalizeRoute(item.path), ...item.items?.map((sub) => normalizeRoute(sub.path)) ?? []]).filter(Boolean);
		const requestedPath = normalizeRoute(location.pathname);
		const allPaths = flattenPaths(items);
		return allPaths.find((path) => path === requestedPath) ?? allPaths.filter((path) => requestedPath.startsWith(path) && path !== "/").sort((a, b) => b.length - a.length)[0];
	}, [location.pathname, items]);
	const isSelected = (0, react.useCallback)((path) => normalizeRoute(path) === activePath, [activePath]);
	const parentContainsSelected = (0, react.useCallback)((item) => item.items?.some((sub) => isSelected(sub.path)) ?? false, [isSelected]);
	(0, react.useEffect)(() => {
		const activeParent = items.find((item) => parentContainsSelected(item));
		if (!activeParent || compactMode) return;
		const activeParentKey = activeParent.path || activeParent.text;
		setExpandedItemKeys((prev) => {
			if (prev.has(activeParentKey)) return prev;
			const next = new Set(prev);
			next.add(activeParentKey);
			return next;
		});
	}, [
		compactMode,
		items,
		parentContainsSelected
	]);
	const toggleExpanded = (0, react.useCallback)((key) => {
		setExpandedItemKeys((prev) => {
			const next = new Set(prev);
			if (next.has(key)) next.delete(key);
			else next.add(key);
			return next;
		});
	}, []);
	const handleSelect = (0, react.useCallback)((path, selected, event) => {
		selectedItemChanged({
			path,
			selected,
			event
		});
	}, [selectedItemChanged]);
	return /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", {
		className: `nb-admin-menu${compactMode ? " compact" : ""}`,
		onPointerDown: openMenu,
		children: [/* @__PURE__ */ (0, react_jsx_runtime.jsx)("div", {
			className: "nb-admin-menu-container theme-dependent",
			children: /* @__PURE__ */ (0, react_jsx_runtime.jsx)("nav", {
				className: "nb-admin-menu__nav",
				"aria-label": "Main navigation",
				children: items.map((item) => {
					const selected = isSelected(item.path);
					const containsSelected = parentContainsSelected(item);
					const itemKey = item.path || item.text;
					const hasChildren = (item.items?.length ?? 0) > 0;
					const expanded = !compactMode && hasChildren && expandedItemKeys.has(itemKey);
					return /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("section", {
						className: `nb-admin-menu__section${containsSelected ? " has-selected-child" : ""}`,
						children: [/* @__PURE__ */ (0, react_jsx_runtime.jsxs)("button", {
							className: `nb-admin-menu__item nb-admin-menu__item--parent${selected ? " is-selected" : ""}`,
							type: "button",
							"aria-current": selected ? "page" : void 0,
							"aria-expanded": hasChildren ? expanded : void 0,
							onClick: (event) => {
								if (hasChildren) {
									toggleExpanded(itemKey);
									return;
								}
								handleSelect(item.path, selected, event);
							},
							children: [
								/* @__PURE__ */ (0, react_jsx_runtime.jsx)("span", {
									className: `nb-admin-menu__icon ${getIconClassName(item.icon)}`,
									"aria-hidden": "true"
								}),
								/* @__PURE__ */ (0, react_jsx_runtime.jsx)("span", {
									className: "nb-admin-menu__text",
									children: item.text
								}),
								hasChildren && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("span", {
									className: `nb-admin-menu__chevron${expanded ? " is-expanded" : ""}`,
									"aria-hidden": "true"
								})
							]
						}), expanded && (item.items?.length ?? 0) > 0 && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("div", {
							className: "nb-admin-menu__children",
							children: item.items.map((subItem) => {
								const childSelected = isSelected(subItem.path);
								return /* @__PURE__ */ (0, react_jsx_runtime.jsx)("button", {
									className: `nb-admin-menu__item nb-admin-menu__item--child${childSelected ? " is-selected" : ""}`,
									type: "button",
									"aria-current": childSelected ? "page" : void 0,
									onClick: (event) => handleSelect(subItem.path, childSelected, event),
									children: /* @__PURE__ */ (0, react_jsx_runtime.jsx)("span", {
										className: "nb-admin-menu__text",
										children: subItem.text
									})
								}, subItem.path || subItem.text);
							})
						})]
					}, itemKey);
				})
			})
		}), footer && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("footer", {
			className: "nb-admin-footer",
			children: footer
		})]
	});
};
//#endregion
//#region assets/admin/AdminShell.tsx
const AdminShell = ({ title, menuItems, headerActions, renderUserMenu, renderThemeSwitcher, footer, children }) => {
	const navigate = (0, react_router_dom.useNavigate)();
	const { isXSmall, isLarge } = useScreenSize();
	const [menuStatus, setMenuStatus] = (0, react.useState)(null);
	const getDefaultMenuOpenState = (0, react.useCallback)(() => isLarge ? 2 : 1, [isLarge]);
	const getMenuOpenState = (0, react.useCallback)((status) => status === null ? getDefaultMenuOpenState() : status, [getDefaultMenuOpenState]);
	const getMenuStatus = (0, react.useCallback)((status) => status === getDefaultMenuOpenState() ? null : status, [getDefaultMenuOpenState]);
	const changeMenuStatus = (0, react.useCallback)((reducerFn) => {
		setMenuStatus((prev) => getMenuStatus(reducerFn(getMenuOpenState(prev)) ?? prev));
	}, [getMenuOpenState, getMenuStatus]);
	const toggleMenu = (0, react.useCallback)((event) => {
		changeMenuStatus((prev) => prev === 1 ? 2 : 1);
		event.stopPropagation();
	}, [changeMenuStatus]);
	const temporaryOpenMenu = (0, react.useCallback)(() => {
		changeMenuStatus((prev) => prev === 1 ? 3 : null);
	}, [changeMenuStatus]);
	const closeMenuFromOverlay = (0, react.useCallback)(() => {
		changeMenuStatus((prev) => prev !== 1 && !isLarge ? 1 : null);
	}, [isLarge, changeMenuStatus]);
	const onNavigationChanged = (0, react.useCallback)(({ path, event, selected }) => {
		if (getMenuOpenState(menuStatus) === 1 || !path || selected) {
			event?.preventDefault();
			return;
		}
		navigate(path);
		if (!isLarge || menuStatus === 3) {
			setMenuStatus(getMenuStatus(1));
			event?.stopPropagation();
		}
	}, [
		navigate,
		menuStatus,
		isLarge,
		getMenuOpenState,
		getMenuStatus
	]);
	(0, react.useEffect)(() => {
		changeMenuStatus(() => menuStatus);
	}, [
		isLarge,
		changeMenuStatus,
		menuStatus
	]);
	const menuOpenState = getMenuOpenState(menuStatus);
	const isMenuOpen = menuOpenState !== 1;
	const isCompact = menuOpenState === 1;
	const bodyClassName = [
		"nb-admin-shell__body nb-admin-layout-body",
		isMenuOpen ? "is-open" : "is-closed",
		isCompact ? "is-compact" : "",
		isLarge ? "is-large" : "is-overlay",
		isXSmall ? "is-xsmall" : ""
	].filter(Boolean).join(" ");
	return /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", {
		className: "nb-admin-shell",
		children: [/* @__PURE__ */ (0, react_jsx_runtime.jsx)(AdminHeader, {
			className: "nb-admin-layout-header",
			menuToggleEnabled: true,
			toggleMenu,
			title,
			actions: headerActions,
			renderUserMenu,
			renderThemeSwitcher
		}), /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", {
			className: bodyClassName,
			children: [
				/* @__PURE__ */ (0, react_jsx_runtime.jsx)("aside", {
					className: "nb-admin-shell__panel",
					"aria-label": "Main menu",
					children: /* @__PURE__ */ (0, react_jsx_runtime.jsx)(AdminSidebarMenu, {
						items: menuItems,
						compactMode: isCompact,
						selectedItemChanged: onNavigationChanged,
						openMenu: temporaryOpenMenu,
						footer
					})
				}),
				!isLarge && isMenuOpen && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("button", {
					className: "nb-admin-shell__scrim",
					type: "button",
					"aria-label": "Close menu",
					onClick: closeMenuFromOverlay
				}),
				/* @__PURE__ */ (0, react_jsx_runtime.jsx)("main", {
					className: "nb-admin-shell__content content",
					children
				})
			]
		})]
	});
};
//#endregion
exports.AdminHeader = AdminHeader;
exports.AdminShell = AdminShell;
exports.AdminSidebarMenu = AdminSidebarMenu;
exports.useScreenSize = useScreenSize;
exports.useScreenSizeClass = useScreenSizeClass;
