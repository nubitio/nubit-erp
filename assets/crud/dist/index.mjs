import React, { createContext, forwardRef, useCallback, useContext, useEffect, useId, useImperativeHandle, useLayoutEffect, useMemo, useReducer, useRef, useState } from "react";
import { createPortal } from "react-dom";
import { useNavigate, useParams, useSearchParams } from "react-router-dom";
import { AppDialog, Button, ConfirmDialog, IconButton } from "@nubit/ui";
import { Fragment, jsx, jsxs } from "react/jsx-runtime";
import { createCrudEvents, createScopedEventBus, getCoreLocale, getCoreTimezone, useCoreHttpClient, useCoreRuntime, useCoreTranslation, useEvents, useMercureSubscription } from "@nubit/core";
import { useQueryClient } from "@tanstack/react-query";
//#region assets/crud/crud/defineResource.ts
const stringResourceCache = /* @__PURE__ */ new Map();
function cacheKey(apiUrl, overrides) {
	if (!overrides) return apiUrl;
	try {
		return `${apiUrl}::${JSON.stringify(overrides)}`;
	} catch {
		return null;
	}
}
/**
* Derives the resource `id` from a URL string.
* '/api/products'         → 'products'
* '/api/voucher-series'   → 'voucher-series'
* 'http://host/a/b/items' → 'items'
*/
function deriveId(apiUrl) {
	const stripped = apiUrl.replace(/\/+$/, "");
	return (stripped.split("/").pop() ?? stripped).split("?")[0];
}
function defineResource(apiUrl, overrides) {
	const key = cacheKey(apiUrl, overrides);
	if (key !== null && stringResourceCache.has(key)) return stringResourceCache.get(key);
	const resource = {
		id: overrides?.id ?? deriveId(apiUrl),
		title: "",
		...overrides,
		apiUrl
	};
	if (key !== null) stringResourceCache.set(key, resource);
	return resource;
}
//#endregion
//#region assets/crud/datagrid/DataGridEvents.ts
const DATA_GRID_EVENTS = {
	SELECTION_CHANGED: "datagrid:selection.changed",
	TOOLBAR_EVENT: "datagrid:toolbar.event"
};
//#endregion
//#region assets/crud/data/ResourceStore.tsx
const ResourceStoreFactoryContext = createContext(null);
function ResourceStoreProvider({ children, factory }) {
	return /* @__PURE__ */ jsx(ResourceStoreFactoryContext.Provider, {
		value: factory,
		children
	});
}
function useResourceStoreFactory() {
	const factory = useContext(ResourceStoreFactoryContext);
	if (!factory) throw new Error("No ResourceStore factory configured. Wrap your app with ResourceStoreProvider or a backend-specific provider such as HydraResourceStoreProvider.");
	return factory;
}
//#endregion
//#region assets/crud/field/FieldType.ts
let FieldType = /* @__PURE__ */ function(FieldType) {
	FieldType["NONE"] = "none";
	FieldType["TEXT"] = "text";
	FieldType["PASSWORD"] = "password";
	FieldType["TEXTAREA"] = "textarea";
	FieldType["DATE"] = "date";
	FieldType["DATETIME"] = "datetime";
	FieldType["NUMBER"] = "number";
	FieldType["CURRENCY"] = "currency";
	FieldType["SELECT"] = "select";
	FieldType["ENUM"] = "enum";
	FieldType["ENTITY"] = "entity";
	FieldType["RADIO"] = "radio";
	FieldType["SWITCH"] = "switch";
	FieldType["CHECKBOX"] = "checkbox";
	FieldType["FILE"] = "file";
	FieldType["TAGS"] = "tags";
	FieldType["HTML"] = "html";
	return FieldType;
}({});
//#endregion
//#region assets/crud/crud/SmartCrudRolesContext.tsx
const SmartCrudRolesContext = createContext([]);
function SmartCrudRolesProvider({ children, roles }) {
	const value = useMemo(() => roles ?? [], [roles]);
	return /* @__PURE__ */ jsx(SmartCrudRolesContext.Provider, {
		value,
		children
	});
}
function useSmartCrudRoles() {
	return useContext(SmartCrudRolesContext);
}
//#endregion
//#region assets/crud/form/FormEvents.ts
const FORM_EVENTS = {
	FIELD_CHANGED: "on:field:changed",
	ROW_ADDED: "on:row:added",
	ROW_REMOVED: "on:row:removed",
	ROW_UPDATED: "on:row:updated",
	LOADING: "on:loading",
	LOADING_DETAIL: "on:loading:detail"
};
const FORM_ERRORS_EVENT = "form-errors";
//#endregion
//#region assets/crud/adapter/HydraAdapter.ts
function trimTrailingSlash(value) {
	return value.replace(/\/+$/, "");
}
function buildIRI(url, value) {
	if (value.startsWith("/")) return value;
	return `${url}/${value}`;
}
/**
* Default backend adapter for API Platform / JSON-LD + Hydra backends.
*
* Conventions assumed:
* - Records carry an `@id` IRI (e.g. `/api/users/5`) as their canonical identifier.
* - Entity fields are serialized as IRI strings in POST/PATCH bodies.
* - Collection responses follow the `hydra:member` / `hydra:totalItems` shape.
* - `_iri` is a synthetic alias for `@id` used internally by the engine.
*/
const HydraAdapter = {
	getRowId(record, idField) {
		const direct = record[idField];
		if (direct !== void 0 && direct !== null) return String(direct);
		const iri = record["@id"] ?? record["_iri"];
		if (iri !== void 0 && iri !== null) return String(iri);
		return String(record["id"] ?? "");
	},
	buildItemUrl(baseUrl, id) {
		const str = String(id);
		if (str.startsWith("/")) return str;
		return `${trimTrailingSlash(baseUrl)}/${str}`;
	},
	serializeEntityRef(field, rawValue) {
		if (rawValue === null || rawValue === void 0 || rawValue === "" || rawValue === -999) return;
		if (typeof rawValue === "object") {
			const entity = rawValue;
			const atId = entity["@id"];
			if (typeof atId === "string") return buildIRI(field.url ?? "", atId);
			const idValue = entity["id"];
			const resolvedId = idValue !== void 0 && idValue !== null ? String(idValue) : String(entity[field.valueField]);
			return buildIRI(field.url ?? "", resolvedId);
		}
		return buildIRI(field.url ?? "", String(rawValue));
	},
	normalizeEntityValue(rawValue, field) {
		if (typeof rawValue === "string") return field.valueField === "_iri" ? rawValue : rawValue.split("/").pop();
		if (typeof rawValue === "object" && rawValue !== null) {
			const entity = rawValue;
			const atId = entity["@id"];
			if (typeof atId === "string") return field.valueField === "_iri" ? atId : atId.split("/").pop() ?? atId;
			const directValue = entity[field.valueField];
			if (directValue !== void 0 && directValue !== null) return directValue;
		}
		return rawValue;
	},
	getEntityOptionKey(item, field) {
		if (field.valueField === "_iri") return item["_iri"] ?? item["@id"] ?? item["id"];
		return item[field.valueField] ?? item["value"] ?? item["id"] ?? item["@id"];
	},
	parseListResponse(response) {
		const r = response;
		const member = r["hydra:member"];
		if (Array.isArray(member)) return {
			items: member,
			total: Number(r["hydra:totalItems"] ?? member.length)
		};
		if (Array.isArray(response)) return {
			items: response,
			total: response.length
		};
		return {
			items: [],
			total: 0
		};
	},
	synthesizeEntityKey(field, entityValue) {
		if (!field.url) return void 0;
		const base = trimTrailingSlash(field.url);
		const directId = entityValue["id"];
		if (typeof directId === "string" || typeof directId === "number") return `${base}/${directId}`;
		const directValue = entityValue[field.valueField];
		if (field.valueField !== "_iri" && (typeof directValue === "string" || typeof directValue === "number")) return `${base}/${directValue}`;
		for (const key of [
			"code",
			"uuid",
			"slug"
		]) {
			const candidate = entityValue[key];
			if (typeof candidate === "string" || typeof candidate === "number") return `${base}/${candidate}`;
		}
	}
};
//#endregion
//#region assets/crud/form/FormDataTransform.ts
function upsertPrependData(store, field, item) {
	const existing = store.get(field.name);
	if (existing) {
		const nextId = item[field.valueField];
		if (!existing.some((candidate) => candidate[field.valueField] === nextId)) existing.unshift(item);
		return;
	}
	store.set(field.name, [item]);
}
/**
* Builds an empty row from field definitions, applying default values.
*/
function buildEmptyRow(fields) {
	const row = {};
	fields.forEach((field) => {
		if (field.defaultValue !== void 0) row[field.name] = field.defaultValue instanceof Function ? field.defaultValue() : field.defaultValue;
		else row[field.name] = void 0;
	});
	return row;
}
/**
* Normalizes raw API data into the shape expected by the active form editors.
*
* - FILE fields are stripped (uploaded separately)
* - PASSWORD fields are blanked
* - DATE fields remain YYYY-MM-DD strings
* - ENTITY fields are normalized to their scalar key for select-style editors
*/
function normalizeFormData(data, fields, adapter = HydraAdapter, prependDataByField) {
	const row = { ...data };
	fields.forEach((field) => {
		if (field.type === "file") delete row[field.name];
		if (field.type === "password") row[field.name] = "";
		if (field.type === "date") {
			const rawValue = row[field.name];
			if (typeof rawValue === "string") row[field.name] = rawValue.slice(0, 10);
			else if (rawValue instanceof Date) row[field.name] = rawValue.toLocaleDateString("en-CA", { timeZone: getCoreTimezone() });
			else row[field.name] = null;
		}
		if (field.type === "entity") normalizeEntityField(row, field, adapter, prependDataByField);
	});
	return row;
}
function normalizeEntityField(row, field, adapter, prependDataByField) {
	const rawValue = row[field.name];
	if (typeof rawValue === "object" && rawValue !== null) {
		const entityValue = { ...rawValue };
		if (field.valueField === "_iri") {
			const synthesized = adapter.synthesizeEntityKey(field, entityValue);
			if (synthesized && typeof entityValue["_iri"] !== "string" && typeof entityValue["@id"] !== "string") entityValue["_iri"] = synthesized;
		}
		if (prependDataByField) upsertPrependData(prependDataByField, field, entityValue);
	}
	const normalized = adapter.normalizeEntityValue(rawValue, field);
	row[field.name] = normalized ?? null;
	if (row[field.name] === null || row[field.name] === void 0) {
		const fromMap = prependDataByField?.get(field.name);
		row[field.name] = fromMap?.[0] ?? field.loadOptions[0]?.prependData?.[0] ?? null;
	}
}
//#endregion
//#region assets/crud/form/safeRandomId.ts
/**
* Generate a unique-enough id string for internal React keys.
*
* Prefers the standards `crypto.randomUUID()` when available, but falls back to
* a Math.random-based token so the engine never throws in environments where
* `crypto.randomUUID` is undefined: insecure (non-HTTPS) browser contexts,
* older browsers, server-side rendering, and some test runners.
*
* These ids are used only as stable list keys for detail-grid rows — they are
* never persisted or sent to the backend — so the fallback's weaker uniqueness
* guarantees are acceptable.
*/
function safeRandomId() {
	const c = typeof globalThis !== "undefined" ? globalThis.crypto : void 0;
	if (c && typeof c.randomUUID === "function") return c.randomUUID();
	return `rid-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`;
}
//#endregion
//#region assets/crud/form/FormLayoutModel.ts
function buildFormLayoutModel(fields, formLayout, overflowLabel) {
	const pickFields = (names) => fields.filter((field) => names.includes(field.name));
	const remainingFields = (assignedNames) => fields.filter((field) => !assignedNames.includes(field.name));
	if (formLayout?.type === "tabs") {
		const overflow = remainingFields(formLayout.tabs.flatMap((tab) => tab.fields));
		return {
			type: "tabs",
			tabs: formLayout.tabs.map((tab) => ({
				label: tab.label,
				icon: tab.icon,
				fields: pickFields(tab.fields)
			})),
			overflow: overflow.length > 0 ? {
				label: overflowLabel,
				fields: overflow
			} : void 0
		};
	}
	if (formLayout?.type === "sections") {
		const overflow = remainingFields(formLayout.sections.flatMap((section) => section.fields));
		return {
			type: "sections",
			sections: formLayout.sections.map((section) => ({
				label: section.label,
				collapsible: section.collapsible,
				defaultCollapsed: section.defaultCollapsed,
				fields: pickFields(section.fields)
			})),
			overflow: overflow.length > 0 ? {
				label: overflowLabel,
				fields: overflow
			} : void 0
		};
	}
	return {
		type: "plain",
		fields
	};
}
//#endregion
//#region assets/crud/form/serializeFormData.ts
/**
* Pure serialization of form data before HTTP submission.
*
* Transforms raw form field values into the shape expected by the API:
* - Applies uploaded file references
* - Evaluates computed fields
* - Resolves ENTITY fields via the BackendAdapter (default: Hydra IRIs)
* - Formats DATE fields as YYYY-MM-DD business dates, with no timezone conversion
* - Coerces numeric fields
* - Strips NONE/FILE fields as appropriate
*
* This function is extracted from useFormSubmit for testability.
*/
function serializeFormFields(rawData, fields, ctx) {
	const formData = { ...rawData };
	ctx.uploadedFiles.forEach((file) => {
		formData[file.name] = file.iri;
	});
	fields.forEach((field) => {
		if (!field.computed) return;
		const computedValue = field.computed(formData);
		if (computedValue !== void 0) formData[field.name] = computedValue;
	});
	const adapter = ctx.adapter ?? HydraAdapter;
	fields.forEach((field) => {
		if (field.type === "entity" || field.type === "tags") {
			serializeEntityField(formData, field, adapter);
			return;
		}
		if (field.type === "file") {
			serializeFileField(formData, field, ctx);
			return;
		}
		if (field.type === "none") {
			if (!field.isIdentity) delete formData[field.name];
			return;
		}
		if (field.type === "date") {
			serializeDateField(formData, field);
			return;
		}
		if (field.type === "text" && field.valueType === "number") formData[field.name] = Number(formData[field.name]);
		if (field.type === "number" || field.type === "currency") serializeNumericField(formData, field);
	});
	return formData;
}
function serializeEntityField(formData, field, adapter) {
	const val = formData[field.name];
	if (field.multiple) {
		if (Array.isArray(val)) formData[field.name] = val.map((item) => adapter.serializeEntityRef(field, item));
		else if (val !== null && val !== void 0 && val !== "") {
			const serialized = adapter.serializeEntityRef(field, val);
			formData[field.name] = serialized !== void 0 ? [serialized] : [];
		} else delete formData[field.name];
		return;
	}
	const serialized = adapter.serializeEntityRef(field, val);
	if (serialized === void 0) delete formData[field.name];
	else formData[field.name] = serialized;
}
function serializeFileField(formData, field, ctx) {
	if (ctx.format === "multipart" && ctx.getFieldValue) {
		const files = ctx.getFieldValue(field.name);
		if (files && files.length > 0) formData[field.name] = files[0];
		else delete formData[field.name];
		return;
	}
	if (Array.isArray(formData[field.name])) delete formData[field.name];
}
function serializeDateField(formData, field) {
	const val = formData[field.name];
	if (val === null || val === void 0 || val === "") {
		delete formData[field.name];
		return;
	}
	if (typeof val === "string" && /^\d{4}-\d{2}-\d{2}$/.test(val)) {
		formData[field.name] = val;
		return;
	}
	if (val instanceof Date) {
		formData[field.name] = new Intl.DateTimeFormat("en-CA", { timeZone: getCoreTimezone() }).format(val);
		return;
	}
	formData[field.name] = String(val).slice(0, 10);
}
function serializeNumericField(formData, field) {
	if (field.isIdentity) return;
	if (field.sendAsString) {
		const val = formData[field.name];
		formData[field.name] = val != null ? Number(val).toFixed(field.precision || 2) : null;
	} else formData[field.name] = Number(formData[field.name]);
}
/**
* Serializes detail grid rows (entity refs, numeric coercion, identity cleanup).
* Pure function — operates on an array of row records.
*/
function serializeDetailRows(rows, detailFields, detailIdField, isEditMode, adapter = HydraAdapter) {
	const details = JSON.parse(JSON.stringify(rows));
	detailFields.forEach((field) => {
		if (field.type === "entity") details.forEach((detail) => {
			const serialized = adapter.serializeEntityRef(field, detail[field.name]);
			if (serialized === void 0) delete detail[field.name];
			else detail[field.name] = serialized;
		});
		if (field.type === "number" || field.type === "currency") details.forEach((detail) => {
			if (field.sendAsString) {
				const val = detail[field.name];
				detail[field.name] = val != null ? Number(val).toFixed(field.precision || 2) : null;
			} else detail[field.name] = Number(detail[field.name]);
		});
	});
	details.forEach((detail) => {
		if (!isEditMode) delete detail[detailIdField];
		else if (typeof detail[detailIdField] === "string") delete detail[detailIdField];
	});
	return details;
}
//#endregion
//#region assets/crud/form/useFormSubmit.ts
/**
* Returns helpers for form serialization and HTTP submit/delete operations.
* Delegates pure serialization to `serializeFormFields` / `serializeDetailRows`.
*/
const useFormSubmit = (options, accessors, emit, validateFn) => {
	const { t } = useCoreTranslation();
	const { confirm } = useCoreRuntime();
	const adapter = options.adapter ?? HydraAdapter;
	const getIdField = () => options.fields.find((f) => f.isIdentity)?.name ?? "";
	const getDetailIdField = () => options.detailFields?.find((f) => f.isIdentity)?.name ?? "";
	/** Build multipart FormData from a plain object */
	const buildMultipartFormData = (data) => {
		const form = new FormData();
		for (const key in data) {
			const value = data[key];
			if (value != null) form.append(key, value instanceof Blob ? value : String(value));
		}
		return form;
	};
	/** Serialize main form fields for submission */
	const serializeFields = (rawData) => {
		return serializeFormFields(rawData, options.fields, {
			uploadedFiles: accessors.getUploadedFiles(),
			getFieldValue: accessors.getFieldValue,
			format: options.format,
			adapter
		});
	};
	/** Append serialized detail grid rows to formData */
	const appendDetailRows = (formData) => {
		if (!options.detailFields) return;
		formData[options.detailPropertyName] = serializeDetailRows(accessors.getDetailRows(), options.detailFields, getDetailIdField(), accessors.isEditMode(), adapter);
	};
	/** Handle the SAVE event: validate → serialize → POST or PATCH */
	const handleSave = () => {
		if (!validateFn()) return;
		const { LOADING: loadingEvent, SUCCESS: successEvent } = options.events ?? {};
		if (loadingEvent) emit(loadingEvent, true);
		options.onLoadingChange?.(true);
		const formData = serializeFields(accessors.getFormData());
		appendDetailRows(formData);
		const id = formData[getIdField()];
		let payload = formData;
		if (options.format === "multipart") payload = buildMultipartFormData(formData);
		const finalize = () => {
			if (loadingEvent) emit(loadingEvent, false);
			options.onLoadingChange?.(false);
		};
		(accessors.isEditMode() ? options.httpClient.patch(adapter.buildItemUrl(options.url, id), payload) : options.httpClient.post(options.url, payload)).then((response) => {
			if (successEvent) emit(successEvent, response);
			options.onSaveSuccess?.(response);
		}).catch((err) => {
			if (typeof err === "object" && err !== null && "status" in err && err.status === 422 && "data" in err && typeof err.data === "object" && err.data !== null && "violations" in err.data) emit(options.formErrorsEvent ?? "form-errors", err.data.violations);
			options.onSaveError?.(err);
		}).finally(finalize);
	};
	/** Handle the DELETE event: confirm dialog → DELETE HTTP */
	const handleDelete = (payload) => {
		const { LOADING: loadingEvent, SUCCESS: successEvent } = options.events ?? {};
		if (!confirm(`${t("dialog.confirmDeleteTitle")}\n\n${t("dialog.confirmDelete")}`)) return;
		if (loadingEvent) emit(loadingEvent, true);
		options.onLoadingChange?.(true);
		const finalize = () => {
			if (loadingEvent) emit(loadingEvent, false);
			options.onLoadingChange?.(false);
		};
		options.httpClient.delete(adapter.buildItemUrl(options.url, payload.row[getIdField()])).then((response) => {
			if (successEvent) emit(successEvent, response);
			options.onDeleteSuccess?.(response);
		}).catch((err) => {
			options.onDeleteError?.(err);
		}).finally(finalize);
	};
	return {
		handleSave,
		handleDelete,
		serializeFields,
		appendDetailRows
	};
};
//#endregion
//#region assets/crud/form/FormDetailValidation.ts
function validateDetailRows(detailRows, detailFields) {
	return detailRows.every((row) => detailFields.every((field) => {
		if (field.isIdentity || field.hidden || !field.visible) return true;
		const value = row[field.name];
		if (field.required && (value === null || value === void 0 || value === "")) return false;
		if (field.type === "number" || field.type === "currency") {
			const numericValue = Number(value ?? 0);
			if (field.required && Number.isNaN(numericValue)) return false;
			const rangeValidators = field.validators.filter((validator) => validator.type === "range");
			for (const validator of rangeValidators) {
				const { min, max } = validator.options;
				if (min !== void 0 && numericValue < min) return false;
				if (max !== void 0 && numericValue > max) return false;
			}
		}
		return true;
	}));
}
//#endregion
//#region assets/crud/form/useFormValidation.ts
function useFormValidation({ accessors, detailFields, detailNoDataText, requiredDetail, validationErrorText }) {
	const { notify } = useCoreRuntime();
	return () => {
		const formValid = accessors.validateForm();
		let gridValid = !accessors.hasPendingDetailEdits();
		let detailRequiredMissing = false;
		let detailRowsValid = true;
		if (requiredDetail) {
			gridValid = accessors.getDetailRowCount() > 0;
			if (!gridValid) detailRequiredMissing = true;
		}
		if (detailFields) detailRowsValid = validateDetailRows(accessors.getDetailRows(), detailFields);
		if (!formValid || !gridValid || !detailRowsValid) notify(detailRequiredMissing && formValid ? detailNoDataText : validationErrorText, detailRequiredMissing && formValid ? "warning" : "error");
		return formValid && gridValid && detailRowsValid;
	};
}
//#endregion
//#region assets/crud/form/FormApiViolations.ts
function normalizePath(path) {
	return path.replace(/^children\[([^\]]+)\]\.data$/, "$1").replace(/^children\[([^\]]+)\]$/, "$1").replace(/^\[([^\]]+)\]$/, "$1").replace(/\[(\w+)\]/g, ".$1").replace(/^\./, "");
}
function readViolation(value) {
	if (!value || typeof value !== "object") return null;
	const record = value;
	const propertyPath = typeof record["propertyPath"] === "string" ? record["propertyPath"] : void 0;
	const message = typeof record["message"] === "string" ? record["message"] : void 0;
	if (!propertyPath && !message) return null;
	return {
		propertyPath,
		message
	};
}
function mapApiViolations(violations, detailPropertyName = "items", defaultMessage = "Invalid value") {
	const fieldErrors = {};
	const detailErrors = {};
	const unassigned = [];
	if (!Array.isArray(violations)) return {
		fieldErrors,
		detailErrors,
		unassigned
	};
	violations.forEach((item) => {
		const violation = readViolation(item);
		if (!violation) return;
		const message = violation.message ?? defaultMessage;
		const path = violation.propertyPath ? normalizePath(violation.propertyPath) : "";
		if (!path) {
			unassigned.push(message);
			return;
		}
		const parts = path.split(".").filter(Boolean);
		if (parts[0] === detailPropertyName && /^\d+$/.test(parts[1] ?? "") && parts[2]) {
			const rowIndex = Number(parts[1]);
			const fieldName = parts.slice(2).join(".");
			detailErrors[rowIndex] = {
				...detailErrors[rowIndex],
				[fieldName]: message
			};
			return;
		}
		fieldErrors[path] = message;
	});
	return {
		fieldErrors,
		detailErrors,
		unassigned
	};
}
//#endregion
//#region assets/crud/summary/SummaryUtils.ts
function toFiniteNumber(value) {
	if (value === null || value === void 0 || value === "") return null;
	const numeric = Number(value);
	return Number.isFinite(numeric) ? numeric : null;
}
function numericColumnValues(rows, column) {
	if (!column) return [];
	return rows.map((row) => toFiniteNumber(row[column])).filter((value) => value !== null);
}
function computeSummaryValue(rows, item) {
	if (typeof item.summaryType === "function") return item.summaryType({
		rows,
		column: item.column,
		item
	});
	const type = item.summaryType ?? "sum";
	if (type === "custom") return void 0;
	if (type === "count") return item.column ? rows.filter((row) => row[item.column] !== null && row[item.column] !== void 0).length : rows.length;
	const values = numericColumnValues(rows, item.column);
	if (values.length === 0) return 0;
	if (type === "avg") return values.reduce((total, next) => total + next, 0) / values.length;
	if (type === "min") return Math.min(...values);
	if (type === "max") return Math.max(...values);
	return values.reduce((total, next) => total + next, 0);
}
function formatSummaryValue(value, item) {
	if (typeof item.valueFormat === "function") return item.valueFormat(value, item);
	if (typeof value !== "number") return value == null ? "" : String(value);
	const precision = item.precision ?? (item.valueFormat === "currency" || item.valueFormat === "fixedPoint" ? 2 : void 0);
	const baseOptions = { ...precision !== void 0 ? {
		minimumFractionDigits: precision,
		maximumFractionDigits: precision
	} : void 0 };
	if (item.valueFormat === "currency") return new Intl.NumberFormat(getCoreLocale(), {
		...baseOptions,
		style: "currency",
		currency: item.currency ?? "PEN",
		currencyDisplay: item.currencyDisplay ?? "narrowSymbol"
	}).format(value);
	if (item.valueFormat === "percent") return new Intl.NumberFormat(getCoreLocale(), {
		...baseOptions,
		style: "percent"
	}).format(value);
	if (item.valueFormat && typeof item.valueFormat === "object") return new Intl.NumberFormat(getCoreLocale(), item.valueFormat).format(value);
	if (item.valueFormat === "fixedPoint" || item.valueFormat === "decimal") return new Intl.NumberFormat(getCoreLocale(), baseOptions).format(value);
	return String(value);
}
function resolveSummaryText(rows, item) {
	const value = computeSummaryValue(rows, item);
	const valueText = formatSummaryValue(value, item);
	const context = {
		value,
		valueText,
		item
	};
	if (item.customizeText) return item.customizeText(context);
	if (item.displayFormat) return item.displayFormat.replace("{0}", valueText);
	return valueText;
}
//#endregion
//#region assets/crud/form/NativeFormView.tsx
function createRemoteSource(field, httpClient, resourceStoreFactory, prependData) {
	const options = prependData?.length ? [{ prependData }, ...field.loadOptions] : field.loadOptions;
	return resourceStoreFactory({
		url: field.url ?? "",
		idField: field.valueField,
		byKeyUrl: field.byKeyUrl,
		defaultFilterRules: fieldFilters(field),
		options,
		iriMode: field.valueField === "_iri",
		httpClient
	});
}
function fieldKeyValue(field, item) {
	if (field.valueField === "_iri") return item["_iri"] ?? item["@id"] ?? item["id"];
	return item[field.valueField] ?? item["value"] ?? item["id"] ?? item["@id"];
}
function fieldTextValue(field, item) {
	const value = item[field.textField] ?? item["text"] ?? item["name"] ?? item["businessName"];
	return value == null ? "" : String(value);
}
function fieldFilters(field) {
	if (field.filters.length === 0) return void 0;
	return field.filters.map((filter) => [
		filter.field,
		filter.operator,
		filter.value
	]);
}
function inputValue(value) {
	if (value instanceof Date) return value.toLocaleDateString("en-CA", { timeZone: getCoreTimezone() });
	if (typeof value === "string" || typeof value === "number") return value;
	return value == null ? "" : String(value);
}
function isEmptyValue(value) {
	return value === null || value === void 0 || value === "" || Array.isArray(value) && value.length === 0;
}
function fieldSearchExpr(field) {
	if (field.searchExpr && field.searchExpr.length > 0) return field.searchExpr;
	return [field.textField].filter(Boolean);
}
function colSpan(field) {
	return { "--native-form-col": String(field.col ?? 12) };
}
const FIELD_BUTTON_ICONS = {
	search: "ph ph-magnifying-glass",
	add: "ph ph-plus",
	clear: "ph ph-x"
};
function resolveButtonIcon(icon) {
	if (!icon) return "ph ph-magnifying-glass";
	if (icon.startsWith("ph ")) return icon;
	if (icon.startsWith("ph-")) return `ph ${icon}`;
	return FIELD_BUTTON_ICONS[icon] ?? "ph ph-magnifying-glass";
}
const LOOKUP_OPENED_EVENT = "lookup:opened";
function useLookupDropdown() {
	const [open, setOpen] = useState(false);
	const [draftQuery, setDraftQuery] = useState(null);
	const containerRef = useRef(null);
	const menuRef = useRef(null);
	const idRef = useRef(Symbol());
	const [menuStyle, setMenuStyle] = useState({
		position: "fixed",
		visibility: "hidden",
		margin: 0
	});
	const getPositionedMenuStyle = useCallback(() => {
		if (!containerRef.current) return {
			position: "fixed",
			visibility: "hidden",
			margin: 0
		};
		const rect = containerRef.current.getBoundingClientRect();
		const left = Math.max(8, Math.min(rect.left, window.innerWidth - rect.width - 8));
		const spaceBelow = window.innerHeight - rect.bottom;
		const spaceAbove = rect.top;
		if (spaceBelow < 250 && spaceAbove > spaceBelow) return {
			position: "fixed",
			left: `${left}px`,
			width: `${rect.width}px`,
			bottom: `${window.innerHeight - rect.top + 4}px`,
			top: "auto",
			margin: 0,
			zIndex: 9999,
			visibility: "visible"
		};
		return {
			position: "fixed",
			left: `${left}px`,
			width: `${rect.width}px`,
			top: `${rect.bottom + 4}px`,
			bottom: "auto",
			margin: 0,
			zIndex: 9999,
			visibility: "visible"
		};
	}, []);
	useEffect(() => {
		if (!open || !containerRef.current) return;
		const container = containerRef.current;
		const checkAriaHidden = () => {
			let el = container.parentElement;
			while (el) {
				if (el.getAttribute("aria-hidden") === "true") {
					setOpen(false);
					return;
				}
				el = el.parentElement;
			}
		};
		const mo = new MutationObserver(checkAriaHidden);
		mo.observe(document.body, {
			attributes: true,
			attributeFilter: ["aria-hidden"],
			subtree: true
		});
		return () => mo.disconnect();
	}, [open]);
	useEffect(() => {
		const id = idRef.current;
		const handler = (e) => {
			if (e.detail.id !== id) setOpen(false);
		};
		document.addEventListener(LOOKUP_OPENED_EVENT, handler);
		return () => document.removeEventListener(LOOKUP_OPENED_EVENT, handler);
	}, []);
	useLayoutEffect(() => {
		if (!open) return;
		document.dispatchEvent(new CustomEvent(LOOKUP_OPENED_EVENT, { detail: { id: idRef.current } }));
	}, [open]);
	useEffect(() => {
		if (!open) return;
		const handler = (e) => {
			const target = e.target;
			if (!containerRef.current?.contains(target) && !menuRef.current?.contains(target)) {
				setOpen(false);
				setDraftQuery(null);
			}
		};
		document.addEventListener("mousedown", handler);
		return () => document.removeEventListener("mousedown", handler);
	}, [open]);
	useLayoutEffect(() => {
		if (!open) return;
		const updatePosition = () => {
			if (!containerRef.current) return;
			const rect = containerRef.current.getBoundingClientRect();
			if (rect.top >= window.innerHeight || rect.bottom <= 0 || rect.left >= window.innerWidth || rect.right <= 0) {
				setOpen(false);
				return;
			}
			setMenuStyle(getPositionedMenuStyle());
		};
		updatePosition();
		window.addEventListener("resize", updatePosition);
		window.addEventListener("scroll", updatePosition, {
			capture: true,
			passive: true
		});
		return () => {
			window.removeEventListener("resize", updatePosition);
			window.removeEventListener("scroll", updatePosition, { capture: true });
		};
	}, [getPositionedMenuStyle, open]);
	return {
		open,
		setOpen: useCallback((value) => {
			const next = typeof value === "function" ? value(open) : value;
			if (next) setMenuStyle(getPositionedMenuStyle());
			setOpen(next);
		}, [getPositionedMenuStyle, open]),
		draftQuery,
		setDraftQuery,
		containerRef,
		menuRef,
		menuStyle
	};
}
function LookupDropdown({ children, className, containerRef, disabled, id, label, menuRef, menuStyle, normalizedValue, onClear, onQueryChange, onScroll, open, query, readOnly, required, setDraftQuery, setOpen }) {
	const blurTimer = useRef(void 0);
	return /* @__PURE__ */ jsxs("div", {
		ref: containerRef,
		className: `native-form__lookup${normalizedValue !== "" ? " has-value" : ""}`,
		children: [
			/* @__PURE__ */ jsx("input", {
				autoComplete: "off",
				className,
				disabled,
				id,
				readOnly,
				required,
				role: "combobox",
				"aria-autocomplete": "list",
				"aria-expanded": open,
				value: query,
				onBlur: () => {
					blurTimer.current = setTimeout(() => {
						setOpen(false);
						setDraftQuery(null);
					}, 120);
				},
				onChange: (event) => onQueryChange(event.currentTarget.value),
				onFocus: () => {
					clearTimeout(blurTimer.current);
					setOpen(true);
				}
			}),
			!disabled && !readOnly && normalizedValue !== "" && /* @__PURE__ */ jsx("button", {
				type: "button",
				className: "native-form__lookup-clear",
				"aria-label": `Limpiar ${label}`,
				onMouseDown: (e) => e.preventDefault(),
				onClick: onClear,
				children: /* @__PURE__ */ jsx("i", {
					className: "ph ph-x",
					"aria-hidden": "true"
				})
			}),
			!disabled && !readOnly && /* @__PURE__ */ jsx("button", {
				type: "button",
				className: "native-form__lookup-toggle",
				"aria-label": `Abrir ${label}`,
				onMouseDown: (e) => e.preventDefault(),
				onClick: () => {
					clearTimeout(blurTimer.current);
					setOpen((prev) => !prev);
				},
				children: /* @__PURE__ */ jsx("i", {
					className: "ph ph-caret-down",
					"aria-hidden": "true",
					style: {
						transition: "transform 180ms cubic-bezier(0.4, 0, 0.2, 1)",
						transform: open ? "rotate(180deg)" : "rotate(0deg)"
					}
				})
			}),
			open && !disabled && !readOnly && createPortal(/* @__PURE__ */ jsx("div", {
				ref: menuRef,
				className: "native-form__lookup-menu",
				role: "listbox",
				style: menuStyle,
				onScroll,
				onMouseDown: (e) => e.preventDefault(),
				children
			}), document.body)
		]
	});
}
function NativeEntitySelect({ className, disabled, field, httpClient, id, onChange, options, prependData, readOnly, required, value }) {
	const { t } = useCoreTranslation();
	const resourceStoreFactory = useResourceStoreFactory();
	const { open, setOpen, draftQuery, setDraftQuery, containerRef, menuRef, menuStyle } = useLookupDropdown();
	const [searchedItems, setSearchedItems] = useState(null);
	const [selectedItemCache, setSelectedItemCache] = useState(null);
	const [loading, setLoading] = useState(false);
	const [loadingMore, setLoadingMore] = useState(false);
	const [hasMore, setHasMore] = useState(true);
	const normalizedValue = String(value && typeof value === "object" ? fieldKeyValue(field, value) : value ?? "");
	const items = searchedItems ?? options;
	const objectValue = value && typeof value === "object" ? value : null;
	const cachedSelectedItem = selectedItemCache && String(fieldKeyValue(field, selectedItemCache) ?? "") === normalizedValue ? selectedItemCache : void 0;
	const objectSelectedItem = objectValue && String(fieldKeyValue(field, objectValue) ?? "") === normalizedValue ? objectValue : void 0;
	const selectedItem = items.find((item) => String(fieldKeyValue(field, item) ?? "") === normalizedValue) ?? options.find((item) => String(fieldKeyValue(field, item) ?? "") === normalizedValue) ?? cachedSelectedItem ?? objectSelectedItem;
	const selectedText = selectedItem ? fieldTextValue(field, selectedItem) : "";
	const query = draftQuery ?? selectedText;
	useEffect(() => {
		if (!field.url || !field.searchEnabled || !open) return;
		const searchValue = (draftQuery ?? "").trim();
		const timer = setTimeout(() => {
			setLoading(true);
			setHasMore(true);
			createRemoteSource(field, httpClient, resourceStoreFactory, prependData).load({
				take: 50,
				skip: 0,
				searchExpr: fieldSearchExpr(field),
				searchValue: searchValue === "" ? void 0 : searchValue
			}).then((result) => {
				setSearchedItems(result.data);
				setHasMore(result.data.length === 50 && (result.totalCount === void 0 || result.data.length < result.totalCount));
			}).catch(() => {
				setSearchedItems(null);
				setHasMore(false);
			}).finally(() => setLoading(false));
		}, 250);
		return () => clearTimeout(timer);
	}, [
		field,
		httpClient,
		open,
		draftQuery,
		resourceStoreFactory
	]);
	const loadMore = useCallback(() => {
		if (!field.url || !field.searchEnabled || loading || loadingMore || !hasMore) return;
		setLoadingMore(true);
		const searchValue = query.trim();
		const currentCount = searchedItems ? searchedItems.length : 0;
		createRemoteSource(field, httpClient, resourceStoreFactory, prependData).load({
			take: 50,
			skip: currentCount,
			searchExpr: fieldSearchExpr(field),
			searchValue: searchValue === "" ? void 0 : searchValue
		}).then((result) => {
			setSearchedItems((prev) => {
				const nextItems = [...prev ?? [], ...result.data];
				const seen = /* @__PURE__ */ new Set();
				return nextItems.filter((item) => {
					const k = fieldKeyValue(field, item);
					if (seen.has(k)) return false;
					seen.add(k);
					return true;
				});
			});
			const nextTotal = currentCount + result.data.length;
			setHasMore(result.data.length === 50 && (result.totalCount === void 0 || nextTotal < result.totalCount));
		}).catch(() => setHasMore(false)).finally(() => setLoadingMore(false));
	}, [
		field,
		httpClient,
		loading,
		loadingMore,
		hasMore,
		query,
		resourceStoreFactory,
		searchedItems
	]);
	const handleScroll = (event) => {
		const t = event.currentTarget;
		if (t.scrollHeight - t.scrollTop <= t.clientHeight + 20) loadMore();
	};
	const selectItem = (item) => {
		setSelectedItemCache(item);
		onChange(fieldKeyValue(field, item) ?? null, item);
		setDraftQuery(null);
		setOpen(false);
	};
	return /* @__PURE__ */ jsxs(LookupDropdown, {
		className,
		containerRef,
		disabled,
		id,
		label: field.label,
		menuRef,
		menuStyle,
		normalizedValue,
		onClear: () => {
			setSelectedItemCache(null);
			onChange(null, null);
			setDraftQuery("");
			setOpen(true);
		},
		onQueryChange: (v) => {
			setDraftQuery(v);
			setOpen(true);
			if (v === "") onChange(null, null);
		},
		onScroll: handleScroll,
		open,
		query,
		readOnly,
		required,
		setDraftQuery,
		setOpen,
		children: [
			loading && items.length === 0 && /* @__PURE__ */ jsx("div", {
				className: "native-form__lookup-status",
				children: t("form.lookupSearching")
			}),
			!loading && items.length === 0 && /* @__PURE__ */ jsx("div", {
				className: "native-form__lookup-status",
				children: t("form.lookupNoResults")
			}),
			!loading && items.map((item) => {
				const itemValue = fieldKeyValue(field, item);
				return /* @__PURE__ */ jsx("button", {
					type: "button",
					role: "option",
					"aria-selected": String(itemValue ?? "") === normalizedValue,
					className: "native-form__lookup-option",
					onMouseDown: (e) => e.preventDefault(),
					onClick: () => selectItem(item),
					children: fieldTextValue(field, item)
				}, String(itemValue));
			}),
			loadingMore && /* @__PURE__ */ jsx("div", {
				className: "native-form__lookup-status",
				children: t("form.lookupLoadingMore")
			})
		]
	});
}
function NativeEnumSelect({ className, disabled, field, id, onChange, readOnly, required, value }) {
	const { t } = useCoreTranslation();
	const { open, setOpen, draftQuery, setDraftQuery, containerRef, menuRef, menuStyle } = useLookupDropdown();
	const allItems = field.data ?? [];
	const normalizedValue = String(value ?? "");
	const selectedItem = allItems.find((item) => String(item["value"] ?? "") === normalizedValue);
	const selectedText = selectedItem ? String(selectedItem["text"] ?? selectedItem["value"] ?? "") : "";
	const query = draftQuery ?? selectedText;
	const visibleItems = draftQuery != null && draftQuery !== "" ? allItems.filter((item) => String(item["text"] ?? item["value"] ?? "").toLowerCase().includes(draftQuery.toLowerCase())) : allItems;
	return /* @__PURE__ */ jsxs(LookupDropdown, {
		className,
		containerRef,
		disabled,
		id,
		label: field.label,
		menuRef,
		menuStyle,
		normalizedValue,
		onClear: () => {
			onChange(null);
			setDraftQuery("");
			setOpen(true);
		},
		onQueryChange: (v) => {
			setDraftQuery(v);
			setOpen(true);
			if (v === "") onChange(null);
		},
		open,
		query,
		readOnly,
		required,
		setDraftQuery,
		setOpen,
		children: [visibleItems.length === 0 && /* @__PURE__ */ jsx("div", {
			className: "native-form__lookup-status",
			children: t("form.lookupNoResults")
		}), visibleItems.map((item) => {
			const itemValue = String(item["value"] ?? "");
			return /* @__PURE__ */ jsx("button", {
				type: "button",
				role: "option",
				"aria-selected": itemValue === normalizedValue,
				className: "native-form__lookup-option",
				onMouseDown: (e) => e.preventDefault(),
				onClick: () => {
					onChange(item["value"] ?? null);
					setDraftQuery(null);
					setOpen(false);
				},
				children: String(item["text"] ?? item["value"] ?? "")
			}, itemValue);
		})]
	});
}
function validateField(field, value, formData, t) {
	if (field.required && isEmptyValue(value)) return t("form.fieldRequired", { label: field.label });
	for (const rule of field.validators ?? []) {
		if (rule.type === "required" && isEmptyValue(value)) return rule.options.message ?? t("form.fieldRequired", { label: field.label });
		if (rule.type === "email" && typeof value === "string" && value !== "" && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) return rule.options.message ?? t("form.invalidEmail");
		if (rule.type === "numeric" && value !== "" && value != null && Number.isNaN(Number(value))) return rule.options.message ?? t("form.invalidNumeric");
		if (rule.type === "pattern" && typeof value === "string" && !new RegExp(rule.options.pattern).test(value)) return rule.options.message ?? t("form.invalidPattern");
		if (rule.type === "stringLength" && typeof value === "string") {
			if (rule.options.min !== void 0 && value.length < rule.options.min) return rule.options.message ?? t("form.stringTooShort");
			if (rule.options.max !== void 0 && value.length > rule.options.max) return rule.options.message ?? t("form.stringTooLong");
		}
		if (rule.type === "range" && value !== "" && value != null) {
			const numericValue = Number(value);
			if (rule.options.min !== void 0 && numericValue < rule.options.min) return rule.options.message ?? t("form.outOfRange");
			if (rule.options.max !== void 0 && numericValue > rule.options.max) return rule.options.message ?? t("form.outOfRange");
		}
		if (rule.type === "compare") {
			const target = rule.options.comparisonTarget();
			const comparison = rule.options.comparisonType ?? "==";
			if (!(comparison === "==" ? value === target : comparison === "!=" ? value !== target : comparison === ">" ? Number(value) > Number(target) : comparison === "<" ? Number(value) < Number(target) : comparison === ">=" ? Number(value) >= Number(target) : Number(value) <= Number(target))) return rule.options.message ?? t("validation.defaultError");
		}
		if (rule.type === "custom" && !rule.options.validationCallback({
			value,
			data: formData
		})) return rule.options.message ?? t("validation.defaultError");
	}
	return null;
}
function DetailColumnGroup({ allowDeleting, fields, colWidths }) {
	return /* @__PURE__ */ jsxs("colgroup", { children: [fields.map((field) => {
		const width = colWidths[field.name] ?? field.width;
		return /* @__PURE__ */ jsx("col", { style: width ? { width } : void 0 }, field.name);
	}), allowDeleting && /* @__PURE__ */ jsx("col", { className: "native-form__col-actions-col" })] });
}
function DetailSummaryFooter({ allowDeleting, colWidths, fields, rows, scrollRef, summary }) {
	if (!summary?.items.length || summary.visible === false) return null;
	const itemsByColumn = new Map(summary.items.filter((item) => item.column).map((item) => [item.column, item]));
	return /* @__PURE__ */ jsx("div", {
		ref: scrollRef,
		className: "native-form__detail-summary-wrap",
		"aria-hidden": "true",
		children: /* @__PURE__ */ jsxs("table", {
			className: "native-form__detail-table native-form__detail-summary-table",
			children: [/* @__PURE__ */ jsx(DetailColumnGroup, {
				allowDeleting,
				colWidths,
				fields
			}), /* @__PURE__ */ jsx("tbody", {
				className: "native-form__detail-summary",
				children: /* @__PURE__ */ jsxs("tr", { children: [fields.map((field) => {
					const item = itemsByColumn.get(field.name);
					const align = item?.align ?? field.align;
					return /* @__PURE__ */ jsx("td", {
						style: align ? { textAlign: align } : void 0,
						children: item && /* @__PURE__ */ jsxs("div", {
							className: "native-form__detail-summary-cell",
							style: { justifyContent: align === "center" ? "center" : align === "right" ? "flex-end" : "flex-start" },
							children: [item.label && /* @__PURE__ */ jsx("span", {
								className: "native-form__detail-summary-label",
								children: item.label
							}), /* @__PURE__ */ jsx("span", {
								className: "native-form__detail-summary-value",
								children: resolveSummaryText(rows, item)
							})]
						})
					}, field.name);
				}), allowDeleting && /* @__PURE__ */ jsx("td", { className: "native-form__col-actions" })] })
			})]
		})
	});
}
function NativeDetailGrid({ allowAdding = true, allowDeleting = true, allowUpdating = true, detailFields, httpClient, detailErrors = {}, onClearCellError, onRowsChange, onRowsMutated, prependData, remoteOptions, rows, summary }) {
	const { t } = useCoreTranslation();
	const visibleFields = detailFields.filter((field) => field.visible && !field.hidden);
	const rowKeysRef = useRef(rows.map(() => safeRandomId()));
	const tableScrollRef = useRef(null);
	const summaryWrapRef = useRef(null);
	if (rowKeysRef.current.length !== rows.length) rowKeysRef.current = rows.map(() => safeRandomId());
	const [colWidths, setColWidths] = useState({});
	const resizingRef = useRef(null);
	const handleResizeMouseDown = (fieldName) => (e) => {
		e.preventDefault();
		e.stopPropagation();
		const th = e.currentTarget.closest("th");
		if (!th) return;
		resizingRef.current = {
			name: fieldName,
			startX: e.clientX,
			startWidth: th.offsetWidth
		};
		const onMouseMove = (ev) => {
			if (!resizingRef.current) return;
			const next = Math.max(36, resizingRef.current.startWidth + (ev.clientX - resizingRef.current.startX));
			setColWidths((prev) => ({
				...prev,
				[resizingRef.current.name]: next
			}));
		};
		const onMouseUp = () => {
			resizingRef.current = null;
			window.removeEventListener("mousemove", onMouseMove);
			window.removeEventListener("mouseup", onMouseUp);
		};
		window.addEventListener("mousemove", onMouseMove);
		window.addEventListener("mouseup", onMouseUp);
	};
	useLayoutEffect(() => {
		const tableScroll = tableScrollRef.current;
		const summaryWrap = summaryWrapRef.current;
		if (!tableScroll || !summaryWrap) return;
		const syncSummaryScroll = () => {
			summaryWrap.scrollLeft = tableScroll.scrollLeft;
		};
		syncSummaryScroll();
		tableScroll.addEventListener("scroll", syncSummaryScroll, { passive: true });
		return () => tableScroll.removeEventListener("scroll", syncSummaryScroll);
	}, [colWidths]);
	const updateCell = async (rowIndex, field, value) => {
		onClearCellError?.(rowIndex, field.name);
		let nextRow = {
			...rows[rowIndex],
			[field.name]: value
		};
		if (field.onChange) {
			const draft = { ...rows[rowIndex] };
			await field.onChange.call({ defaultSetCellValue: (row, nextValue) => {
				row[field.name] = nextValue;
			} }, draft, value, rows[rowIndex]);
			nextRow = {
				...nextRow,
				...draft
			};
		}
		onRowsChange(rows.map((row, index) => index === rowIndex ? nextRow : row));
		onRowsMutated();
	};
	const addRow = () => {
		rowKeysRef.current.push(safeRandomId());
		onRowsChange([...rows, buildEmptyRow(detailFields)]);
		onRowsMutated();
	};
	const removeRow = (rowIndex) => {
		rowKeysRef.current.splice(rowIndex, 1);
		onRowsChange(rows.filter((_, index) => index !== rowIndex));
		onRowsMutated();
	};
	const renderDetailControl = (row, rowIndex, field) => {
		const value = row[field.name];
		const errorClass = detailErrors[rowIndex]?.[field.name] ? " is-error" : "";
		if (field.formatter && field.type === "none") return field.formatter({
			value,
			data: row,
			rowIndex,
			columnIndex: 0
		});
		if (field.type === "entity") return /* @__PURE__ */ jsx(NativeEntitySelect, {
			className: `native-form__control${errorClass}`,
			disabled: !allowUpdating || field.disabled,
			field,
			httpClient,
			options: remoteOptions[field.name] ?? field.data ?? [],
			prependData: prependData?.get(field.name),
			value,
			onChange: (nextValue) => void updateCell(rowIndex, field, nextValue)
		});
		if (field.type === "select" || field.type === "enum") return /* @__PURE__ */ jsx(NativeEnumSelect, {
			className: `native-form__control${errorClass}`,
			disabled: !allowUpdating || field.disabled,
			field,
			value,
			onChange: (nextValue) => void updateCell(rowIndex, field, nextValue)
		});
		return /* @__PURE__ */ jsx("input", {
			className: `native-form__control${errorClass}`,
			value: inputValue(value),
			readOnly: !allowUpdating || field.readonly,
			disabled: field.disabled,
			type: field.type === "number" || field.type === "currency" ? "number" : "text",
			onChange: (event) => void updateCell(rowIndex, field, event.target.value)
		});
	};
	return /* @__PURE__ */ jsxs("div", {
		className: "native-form__detail",
		children: [/* @__PURE__ */ jsxs("div", {
			className: "native-form__detail-heading",
			children: [/* @__PURE__ */ jsx("h3", {
				className: "native-form__detail-title",
				children: t("form.detailTitle")
			}), allowAdding && /* @__PURE__ */ jsx(IconButton, {
				icon: "ph ph-plus",
				label: "Agregar detalle",
				onClick: addRow
			})]
		}), /* @__PURE__ */ jsxs("div", {
			className: "native-form__detail-table-wrap",
			children: [/* @__PURE__ */ jsx("div", {
				ref: tableScrollRef,
				className: "native-form__detail-table-scroll",
				children: /* @__PURE__ */ jsxs("table", {
					className: "native-form__detail-table",
					children: [
						/* @__PURE__ */ jsx(DetailColumnGroup, {
							allowDeleting,
							colWidths,
							fields: visibleFields
						}),
						/* @__PURE__ */ jsx("thead", { children: /* @__PURE__ */ jsxs("tr", { children: [visibleFields.map((field) => /* @__PURE__ */ jsxs("th", {
							style: field.align ? { textAlign: field.align } : void 0,
							children: [/* @__PURE__ */ jsx("span", {
								className: "native-form__col-header-text",
								children: field.label
							}), /* @__PURE__ */ jsx("span", {
								className: "native-form__col-resize",
								onMouseDown: handleResizeMouseDown(field.name),
								title: "Arrastrar para redimensionar"
							})]
						}, field.name)), allowDeleting && /* @__PURE__ */ jsx("th", { className: "native-form__col-actions" })] }) }),
						/* @__PURE__ */ jsxs("tbody", { children: [rows.length === 0 && /* @__PURE__ */ jsx("tr", { children: /* @__PURE__ */ jsx("td", {
							colSpan: visibleFields.length + (allowDeleting ? 1 : 0),
							className: "native-form__detail-empty",
							children: t("form.detailEmpty")
						}) }), rows.map((row, rowIndex) => {
							const rowKey = rowKeysRef.current[rowIndex];
							return /* @__PURE__ */ jsxs("tr", { children: [visibleFields.map((field) => /* @__PURE__ */ jsxs("td", {
								style: field.align ? { textAlign: field.align } : void 0,
								children: [renderDetailControl(row, rowIndex, field), detailErrors[rowIndex]?.[field.name] && /* @__PURE__ */ jsxs("span", {
									className: "native-form__error",
									children: [/* @__PURE__ */ jsx("i", {
										className: "ph ph-warning-circle",
										"aria-hidden": "true"
									}), detailErrors[rowIndex][field.name]]
								})]
							}, field.name)), allowDeleting && /* @__PURE__ */ jsx("td", {
								className: "native-form__col-actions",
								children: /* @__PURE__ */ jsx(IconButton, {
									icon: "ph ph-trash",
									label: "Eliminar detalle",
									onClick: () => removeRow(rowIndex)
								})
							})] }, rowKey);
						})] })
					]
				})
			}), /* @__PURE__ */ jsx(DetailSummaryFooter, {
				allowDeleting,
				colWidths,
				fields: visibleFields,
				rows,
				scrollRef: summaryWrapRef,
				summary
			})]
		})]
	});
}
const NativeFormView = forwardRef((options, ref) => {
	const { t } = useCoreTranslation();
	const { notify } = useCoreRuntime();
	const httpClient = useCoreHttpClient();
	const resourceStoreFactory = useResourceStoreFactory();
	const [on, emit] = useEvents();
	const scopedFormErrorsEvent = `${FORM_ERRORS_EVENT}:${useId()}`;
	const isEdit = useRef(false);
	const uploadedFiles = useRef([]);
	const [formData, setFormData] = useState(() => buildEmptyRow(options.fields));
	const formDataRef = useRef(formData);
	const [detailRows, setDetailRows] = useState([]);
	const detailRowsRef = useRef(detailRows);
	const [fieldState, setFieldState] = useState({});
	const [errors, setErrors] = useState({});
	const [detailErrors, setDetailErrors] = useState({});
	const [remoteOptions, setRemoteOptions] = useState({});
	const [activeTab, setActiveTab] = useState(0);
	const appliedOperationRef = useRef(null);
	const prependDataRef = useRef(/* @__PURE__ */ new Map());
	const fields = options.fields;
	const onFieldDataChanged = options.onFieldDataChanged;
	const detailPropertyName = options.detailPropertyName ?? "items";
	const computedFieldNames = useMemo(() => fields.filter((field) => field.computed !== void 0).map((field) => field.name), [fields]);
	const formLayoutModel = useMemo(() => buildFormLayoutModel(fields, options.formLayout, t("form.groupOther")), [
		fields,
		options.formLayout,
		t
	]);
	const optionFields = useMemo(() => [...fields, ...options.detailFields ?? []], [fields, options.detailFields]);
	const setNextFormData = useCallback((nextData) => {
		formDataRef.current = nextData;
		setFormData(nextData);
		onFieldDataChanged?.(nextData);
	}, [onFieldDataChanged]);
	const setNextDetailRows = useCallback((nextRows) => {
		detailRowsRef.current = nextRows;
		setDetailRows(nextRows);
	}, []);
	const notifyDetailRowsMutated = useCallback(() => {
		emit(FORM_EVENTS.ROW_UPDATED, detailRowsRef.current);
	}, [emit]);
	const setFieldValue = useCallback((name, value) => {
		const field = fields.find((candidate) => candidate.name === name);
		const nextData = {
			...formDataRef.current,
			[name]: value
		};
		formDataRef.current = nextData;
		setFormData(nextData);
		setErrors((current) => ({
			...current,
			[name]: ""
		}));
		onFieldDataChanged?.(nextData);
		(field?.onChange)?.(value);
	}, [fields, onFieldDataChanged]);
	const clearDetailCellError = useCallback((rowIndex, fieldName) => {
		setDetailErrors((current) => {
			const rowErrors = current[rowIndex];
			if (!rowErrors?.[fieldName]) return current;
			const nextRowErrors = { ...rowErrors };
			delete nextRowErrors[fieldName];
			const next = { ...current };
			if (Object.keys(nextRowErrors).length === 0) delete next[rowIndex];
			else next[rowIndex] = nextRowErrors;
			return next;
		});
	}, []);
	useEffect(() => {
		formDataRef.current = formData;
	}, [formData]);
	useEffect(() => {
		detailRowsRef.current = detailRows;
	}, [detailRows]);
	useEffect(() => {
		optionFields.forEach((field) => {
			if (field.type !== "entity" && field.type !== "tags") return;
			if (!field.url) {
				setRemoteOptions((current) => ({
					...current,
					[field.name]: field.data ?? []
				}));
				return;
			}
			createRemoteSource(field, httpClient, resourceStoreFactory, prependDataRef.current.get(field.name)).load({ take: 50 }).then((result) => {
				setRemoteOptions((current) => ({
					...current,
					[field.name]: result.data
				}));
				if (field.autoSelectIfSingle && result.data.length === 1 && isEmptyValue(formDataRef.current[field.name])) setFieldValue(field.name, fieldKeyValue(field, result.data[0]));
			}).catch(() => {
				setRemoteOptions((current) => ({
					...current,
					[field.name]: field.data ?? []
				}));
			});
		});
	}, [
		httpClient,
		optionFields,
		resourceStoreFactory,
		setFieldValue
	]);
	useEffect(() => {
		fields.forEach((field) => {
			const optionsForField = remoteOptions[field.name];
			if (!field.autoSelectIfSingle || optionsForField?.length !== 1 || !isEmptyValue(formDataRef.current[field.name])) return;
			setFieldValue(field.name, fieldKeyValue(field, optionsForField[0]));
		});
	}, [
		fields,
		formData,
		remoteOptions,
		setFieldValue
	]);
	const validate = useFormValidation({
		accessors: {
			getDetailRowCount: () => detailRowsRef.current.length,
			getDetailRows: () => detailRowsRef.current,
			hasPendingDetailEdits: () => false,
			validateForm: useCallback(() => {
				const nextErrors = {};
				options.fields.forEach((field) => {
					const state = fieldState[field.name];
					if (field.isIdentity || state?.validationEnabled === false || state?.hidden === true || field.hidden || field.visibleOnForm === false) return;
					const error = validateField(field, formDataRef.current[field.name], formDataRef.current, t);
					if (error) nextErrors[field.name] = error;
				});
				setErrors(nextErrors);
				setDetailErrors({});
				return Object.keys(nextErrors).length === 0;
			}, [
				fieldState,
				options.fields,
				t
			])
		},
		detailFields: options.detailFields,
		detailNoDataText: options.requiredDetail ? t("form.detailRequired") : t("form.detailEmpty"),
		requiredDetail: options.requiredDetail,
		validationErrorText: t("form.validationError")
	});
	const submitAccessors = useMemo(() => ({
		getDetailRows: () => detailRowsRef.current,
		getFieldValue: (field) => formDataRef.current[field],
		getFormData: () => formDataRef.current,
		getUploadedFiles: () => uploadedFiles.current,
		isEditMode: () => isEdit.current
	}), []);
	const { handleSave, handleDelete } = useFormSubmit({
		url: options.url,
		detailUrl: options.detailUrl,
		fields: options.fields,
		detailFields: options.detailFields,
		detailPropertyName,
		format: options.format,
		events: options.events,
		formErrorsEvent: scopedFormErrorsEvent,
		httpClient,
		adapter: options.adapter,
		onSaveSuccess: options.onSaveSuccess,
		onSaveError: options.onSaveError,
		onDeleteSuccess: options.onDeleteSuccess,
		onDeleteError: options.onDeleteError,
		onLoadingChange: options.onLoadingChange
	}, submitAccessors, emit, validate);
	const handleSaveRef = useRef(handleSave);
	handleSaveRef.current = handleSave;
	const handleDeleteRef = useRef(handleDelete);
	handleDeleteRef.current = handleDelete;
	const applyAddPayload = useCallback((payload) => {
		uploadedFiles.current = [];
		setErrors({});
		setDetailErrors({});
		setNextDetailRows([]);
		prependDataRef.current = /* @__PURE__ */ new Map();
		setNextFormData(payload ? normalizeFormData(payload, options.fields, options.adapter, prependDataRef.current) : buildEmptyRow(options.fields));
		if (payload && Array.isArray(payload[detailPropertyName])) setNextDetailRows(payload[detailPropertyName]);
		isEdit.current = false;
	}, [
		detailPropertyName,
		options.adapter,
		options.fields,
		setNextDetailRows,
		setNextFormData
	]);
	const applyEditPayload = useCallback((payload) => {
		prependDataRef.current = /* @__PURE__ */ new Map();
		const row = normalizeFormData(payload.row, options.fields, options.adapter, prependDataRef.current);
		uploadedFiles.current = [];
		setErrors({});
		setDetailErrors({});
		setNextFormData(row);
		isEdit.current = true;
		const detailId = row[options.fields.find((field) => field.isIdentity)?.name ?? ""];
		const detailUrl = typeof detailId === "string" || typeof detailId === "number" ? options.detailUrl?.replace("{id}", String(detailId)) : void 0;
		if (!detailUrl) return;
		emit(FORM_EVENTS.LOADING, true);
		httpClient.get(detailUrl).then((response) => setNextDetailRows(response.data)).finally(() => emit(FORM_EVENTS.LOADING, false));
	}, [
		emit,
		httpClient,
		options.adapter,
		options.detailUrl,
		options.fields,
		setNextDetailRows,
		setNextFormData
	]);
	useEffect(() => {
		if (!options.operation) {
			appliedOperationRef.current = null;
			return;
		}
		const operationKey = `${options.operation}:${options.operationVersion ?? 0}`;
		if (appliedOperationRef.current === operationKey) return;
		appliedOperationRef.current = operationKey;
		if (options.operation === "add") {
			applyAddPayload(options.rowData ?? void 0);
			return;
		}
		if (options.operation === "edit" && options.rowData) applyEditPayload({ row: options.rowData });
	}, [
		applyAddPayload,
		applyEditPayload,
		options.operation,
		options.operationVersion,
		options.rowData
	]);
	useEffect(() => {
		const subs = [on(scopedFormErrorsEvent, (formErrors) => {
			const mapped = mapApiViolations(formErrors, detailPropertyName, t("validation.defaultError"));
			notify(t("form.validationError"), "error");
			setErrors((current) => {
				return {
					...current,
					...mapped.fieldErrors
				};
			});
			setDetailErrors(mapped.detailErrors);
		})];
		if (options.events?.ADD) subs.push(on(options.events.ADD, (payload) => applyAddPayload(payload)));
		if (options.events?.EDIT) subs.push(on(options.events.EDIT, (payload) => applyEditPayload(payload)));
		if (options.events?.DELETE) subs.push(on(options.events.DELETE, (payload) => handleDeleteRef.current(payload)));
		if (options.events?.SAVE) subs.push(on(options.events.SAVE, () => handleSaveRef.current()));
		return () => subs.forEach((sub) => sub.unsubscribe());
	}, [scopedFormErrorsEvent]);
	useEffect(() => {
		if (computedFieldNames.length === 0 || !options.computedValues) return;
		let changed = false;
		const nextData = { ...formDataRef.current };
		computedFieldNames.forEach((name) => {
			if (Object.prototype.hasOwnProperty.call(options.computedValues, name) && nextData[name] !== options.computedValues?.[name]) {
				nextData[name] = options.computedValues?.[name];
				changed = true;
			}
		});
		if (changed) setNextFormData(nextData);
	}, [
		computedFieldNames,
		options.computedValues,
		setNextFormData
	]);
	useImperativeHandle(ref, () => ({
		getFormData: () => formDataRef.current,
		getFieldValue: (name) => formDataRef.current[name],
		setFieldValue,
		getDetailData: () => detailRowsRef.current,
		setDetailData: setNextDetailRows,
		saveDetailData: () => void 0,
		reloadDetailData: () => void 0,
		setValues: (data) => {
			prependDataRef.current = /* @__PURE__ */ new Map();
			setNextFormData(normalizeFormData(data, options.fields, void 0, prependDataRef.current));
		},
		save: () => handleSaveRef.current(),
		deleteRow: (row) => handleDeleteRef.current({ row }),
		setReadonly: (field, value) => setFieldState((current) => ({
			...current,
			[field]: {
				...current[field],
				readonly: value
			}
		})),
		setDisabled: (field, value) => setFieldState((current) => ({
			...current,
			[field]: {
				...current[field],
				disabled: value
			}
		})),
		enableValidation: (field) => setFieldState((current) => ({
			...current,
			[field]: {
				...current[field],
				validationEnabled: true
			}
		})),
		disableValidation: (field) => setFieldState((current) => ({
			...current,
			[field]: {
				...current[field],
				validationEnabled: false
			}
		})),
		setError: (field, message) => setErrors((current) => ({
			...current,
			[field]: message
		})),
		showField: (field) => setFieldState((current) => ({
			...current,
			[field]: {
				...current[field],
				hidden: false
			}
		})),
		hideField: (field) => setFieldState((current) => ({
			...current,
			[field]: {
				...current[field],
				hidden: true
			}
		})),
		validate,
		setIsEdit: (value) => {
			isEdit.current = value;
		}
	}), [
		options.fields,
		setFieldValue,
		setNextDetailRows,
		setNextFormData,
		validate
	]);
	const renderField = (field) => {
		const state = fieldState[field.name] ?? {};
		if (field.isIdentity || !field.visibleOnForm || field.hidden || state.hidden) return null;
		const value = formData[field.name];
		const readOnly = state.readonly ?? field.readonly ?? options.editable === false;
		const disabled = state.disabled ?? field.disabled;
		const error = errors[field.name];
		const errorClass = error ? " is-error" : "";
		const commonProps = {
			className: `native-form__control${field.onClick ? " native-form__control--action" : ""}${errorClass}`,
			disabled,
			id: `native-form-${field.name}`,
			name: field.name,
			onClick: field.onClick,
			readOnly,
			required: field.required
		};
		let control;
		if (field.contentRender && typeof field.contentRender === "function") control = field.contentRender({
			data: formData,
			value
		});
		else if (field.type === "textarea" || field.type === "html") control = /* @__PURE__ */ jsx("textarea", {
			...commonProps,
			className: `native-form__control native-form__textarea${errorClass}`,
			value: inputValue(value),
			onChange: (event) => setFieldValue(field.name, event.target.value)
		});
		else if (field.type === "checkbox" || field.type === "switch") {
			const isSwitch = field.type === "switch";
			control = /* @__PURE__ */ jsxs("label", {
				className: isSwitch ? "native-form__switch" : "native-form__checkbox",
				children: [/* @__PURE__ */ jsx("input", {
					type: "checkbox",
					checked: Boolean(value),
					disabled: disabled || readOnly,
					onChange: (event) => setFieldValue(field.name, event.target.checked)
				}), /* @__PURE__ */ jsx("span", {
					className: isSwitch ? "native-form__toggle-label" : void 0,
					children: field.label
				})]
			});
		} else if (field.type === "radio") control = /* @__PURE__ */ jsx("div", {
			className: "native-form__radio-list",
			children: (field.data ?? []).map((item) => {
				const itemValue = item["value"];
				return /* @__PURE__ */ jsxs("label", {
					className: "native-form__radio-item",
					children: [/* @__PURE__ */ jsx("input", {
						type: "radio",
						checked: value === itemValue,
						disabled: disabled || readOnly,
						onChange: () => setFieldValue(field.name, itemValue)
					}), String(item["text"] ?? itemValue)]
				}, String(itemValue));
			})
		});
		else if (field.type === "entity" && !field.multiple) {
			const items = remoteOptions[field.name] ?? field.data ?? [];
			control = /* @__PURE__ */ jsx(NativeEntitySelect, {
				className: `native-form__control${errorClass}`,
				disabled,
				field,
				httpClient,
				id: `native-form-${field.name}`,
				options: items,
				prependData: prependDataRef.current.get(field.name),
				readOnly,
				required: field.required,
				value,
				onChange: (nextValue, item) => {
					setFieldValue(field.name, nextValue);
					field.onSelect?.({
						value: nextValue,
						itemData: item ?? void 0
					});
				}
			});
		} else if (field.type === "enum" || field.type === "select") control = /* @__PURE__ */ jsx(NativeEnumSelect, {
			className: `native-form__control${errorClass}`,
			disabled,
			field,
			id: `native-form-${field.name}`,
			readOnly,
			required: field.required,
			value,
			onChange: (nextValue) => setFieldValue(field.name, nextValue)
		});
		else if (field.type === "tags") {
			const items = remoteOptions[field.name] ?? field.data ?? [];
			control = /* @__PURE__ */ jsx("select", {
				...commonProps,
				multiple: true,
				value: Array.isArray(value) ? value.map(String) : [],
				onChange: (event) => {
					const nextValue = Array.from(event.currentTarget.selectedOptions).map((option) => option.value);
					setFieldValue(field.name, nextValue);
				},
				children: items.map((item) => {
					const itemValue = fieldKeyValue(field, item);
					const itemText = fieldTextValue(field, item);
					return /* @__PURE__ */ jsx("option", {
						value: String(itemValue ?? ""),
						children: itemText
					}, String(itemValue));
				})
			});
		} else if (field.type === "file") control = /* @__PURE__ */ jsx("input", {
			...commonProps,
			type: "file",
			accept: field.accept ?? void 0,
			readOnly: void 0,
			onChange: (event) => {
				setFieldValue(field.name, event.target.files ?? null);
			}
		});
		else {
			const type = field.type === "number" || field.type === "currency" ? "number" : field.type === "date" ? "date" : field.type === "datetime" ? "datetime-local" : field.type === "password" ? "password" : "text";
			const inputControl = /* @__PURE__ */ jsx("input", {
				...commonProps,
				type,
				value: inputValue(value),
				onChange: (event) => setFieldValue(field.name, event.target.value)
			});
			const fieldButtons = Array.isArray(field.buttons) ? field.buttons : [];
			if (fieldButtons.length > 0) {
				const buttonFormApi = {
					getFieldValue: (name) => formDataRef.current[name],
					setFieldValue
				};
				control = /* @__PURE__ */ jsxs("div", {
					className: "native-form__input-group",
					children: [inputControl, /* @__PURE__ */ jsx("div", {
						className: "native-form__input-actions",
						children: fieldButtons.map((button, index) => {
							const buttonOptions = button.options ?? {};
							const buttonLabel = button.name === "search" ? t("form.searchButton") : String(button.name ?? t("form.actionButton"));
							return /* @__PURE__ */ jsx("button", {
								type: "button",
								className: "native-form__input-button",
								disabled: disabled || readOnly,
								"aria-label": buttonLabel,
								title: buttonLabel,
								onClick: (event) => buttonOptions.onClick?.(event, buttonFormApi),
								children: /* @__PURE__ */ jsx("i", {
									className: resolveButtonIcon(buttonOptions.icon),
									"aria-hidden": "true"
								})
							}, String(button.name ?? index));
						})
					})]
				});
			} else control = inputControl;
		}
		return /* @__PURE__ */ jsxs("div", {
			className: "native-form__field",
			style: colSpan(field),
			children: [
				field.type !== "checkbox" && field.type !== "switch" && /* @__PURE__ */ jsx("label", {
					className: `native-form__label${field.required ? " native-form__label--required" : ""}`,
					htmlFor: `native-form-${field.name}`,
					children: field.label
				}),
				control,
				field.helpText && /* @__PURE__ */ jsx("span", {
					className: "native-form__label",
					children: field.helpText
				}),
				error && /* @__PURE__ */ jsxs("span", {
					className: "native-form__error",
					children: [/* @__PURE__ */ jsx("i", {
						className: "ph ph-warning-circle",
						"aria-hidden": "true"
					}), error]
				})
			]
		}, field.name);
	};
	const renderFields = (fields) => /* @__PURE__ */ jsx("div", {
		className: "native-form__grid",
		children: fields.map(renderField)
	});
	const renderGroup = (group) => /* @__PURE__ */ jsxs("section", {
		className: "native-form__section",
		children: [/* @__PURE__ */ jsx("h3", {
			className: "native-form__section-title",
			children: group.label
		}), renderFields(group.fields)]
	}, group.label);
	const renderLayout = (layout) => {
		if (layout.type === "tabs") {
			const groups = [...layout.tabs, ...layout.overflow ? [layout.overflow] : []];
			const current = groups[activeTab] ?? groups[0];
			return /* @__PURE__ */ jsxs("div", {
				className: "native-form__tabs",
				children: [/* @__PURE__ */ jsx("div", {
					className: "native-form__tabs-nav",
					children: groups.map((group, index) => /* @__PURE__ */ jsx("button", {
						type: "button",
						className: `native-form__tab-button${index === activeTab ? " native-form__tab-button--active" : ""}`,
						onClick: () => setActiveTab(index),
						children: group.label
					}, group.label))
				}), current && renderFields(current.fields)]
			});
		}
		if (layout.type === "sections") return /* @__PURE__ */ jsxs(Fragment, { children: [layout.sections.map(renderGroup), layout.overflow && renderGroup(layout.overflow)] });
		return renderFields(layout.fields);
	};
	const detailGrid = options.detailFields ? /* @__PURE__ */ jsx(NativeDetailGrid, {
		allowAdding: options.allowAdding,
		allowDeleting: options.allowDeleting,
		allowUpdating: options.allowUpdating,
		detailFields: options.detailFields,
		detailErrors,
		httpClient,
		onClearCellError: clearDetailCellError,
		onRowsMutated: notifyDetailRowsMutated,
		prependData: prependDataRef.current,
		remoteOptions,
		rows: detailRows,
		summary: options.detailSummary,
		onRowsChange: setNextDetailRows
	}) : null;
	return /* @__PURE__ */ jsx("div", {
		className: `native-form${options.detailFields ? " native-form--with-detail" : ""} ${options.className ?? ""}`,
		children: options.detailFields ? /* @__PURE__ */ jsxs("div", {
			className: "native-form__master-detail",
			children: [/* @__PURE__ */ jsx("div", {
				className: "native-form__master-panel",
				children: renderLayout(formLayoutModel)
			}), /* @__PURE__ */ jsx("div", {
				className: "native-form__detail-panel",
				children: detailGrid
			})]
		}) : renderLayout(formLayoutModel)
	});
});
NativeFormView.displayName = "NativeFormView";
//#endregion
//#region assets/crud/datagrid/NativeDataGridView.tsx
function getPageRange(current, total) {
	if (total <= 7) return Array.from({ length: total }, (_, i) => i);
	const result = [0];
	if (current > 3) result.push(null);
	for (let i = Math.max(1, current - 1); i <= Math.min(total - 2, current + 1); i++) result.push(i);
	if (current < total - 4) result.push(null);
	result.push(total - 1);
	return result;
}
const PAGE_SIZE_OPTIONS = [
	10,
	20,
	50
];
function getIdField(fields) {
	return fields.find((field) => field.isIdentity)?.name ?? "id";
}
function getPrimitiveDisplay(value, yesLabel = "Yes", noLabel = "No") {
	if (value === null || value === void 0) return "";
	if (value instanceof Date) return value.toLocaleDateString(getCoreLocale(), { timeZone: getCoreTimezone() });
	if (typeof value === "boolean") return value ? yesLabel : noLabel;
	if (typeof value === "object") return "";
	return String(value);
}
function getDateDisplay(value) {
	if (!value) return "";
	const d = value instanceof Date ? value : new Date(String(value));
	if (isNaN(d.getTime())) return String(value);
	return d.toLocaleDateString(getCoreLocale(), { timeZone: getCoreTimezone() });
}
function getDateTimeDisplay(value) {
	if (!value) return "";
	const d = value instanceof Date ? value : new Date(String(value));
	if (isNaN(d.getTime())) return String(value);
	return d.toLocaleString(getCoreLocale(), {
		timeZone: getCoreTimezone(),
		dateStyle: "short",
		timeStyle: "short"
	});
}
function getEntityDisplayValue(field, value, entityOptions) {
	if (value && typeof value === "object") {
		const record = value;
		return getPrimitiveDisplay(record[field.textField] ?? record["name"] ?? record["businessName"]);
	}
	if (value && typeof value === "string" && entityOptions) {
		const match = entityOptions.find((item) => item[field.valueField] === value || item["@id"] === value || item["_iri"] === value);
		if (match) {
			const display = match[field.textField] ?? match["name"] ?? match["businessName"];
			if (display != null) return getPrimitiveDisplay(display);
		}
	}
	return getPrimitiveDisplay(value);
}
function getEnumDisplayValue(field, value) {
	const match = Array.isArray(field.data) ? field.data.find((item) => item["value"] === value || item[field.valueField] === value) : void 0;
	return getPrimitiveDisplay(match?.["text"] ?? match?.[field.textField] ?? value);
}
function getCellText(field, row, entityOptions, yesLabel = "Yes", noLabel = "No") {
	if (field.formatter) return "";
	const value = row[field.name];
	if (field.type === "entity") return getEntityDisplayValue(field, value, entityOptions);
	if (field.type === "enum" || field.type === "switch") return getEnumDisplayValue(field, value);
	if (field.type === "date") return getDateDisplay(value);
	if (field.type === "datetime") return getDateTimeDisplay(value);
	return getPrimitiveDisplay(value, yesLabel, noLabel);
}
function renderCell(field, row, rowIndex, columnIndex, entityOptions, yesLabel = "Yes", noLabel = "No") {
	const value = row[field.name];
	if (field.formatter) return field.formatter({
		value,
		data: row,
		rowIndex,
		columnIndex
	});
	if (field.type === "entity") return getEntityDisplayValue(field, value, entityOptions);
	if (field.type === "enum" || field.type === "switch") return getEnumDisplayValue(field, value);
	if (field.type === "date") return getDateDisplay(value);
	if (field.type === "datetime") return getDateTimeDisplay(value);
	return getPrimitiveDisplay(value, yesLabel, noLabel);
}
function normalizeIcon(icon) {
	if (!icon) return void 0;
	return icon.includes("ph ") || icon.startsWith("ph-") ? icon : `ph-${icon}`;
}
function getToolbarKey(action, index) {
	return action.key ?? action.text ?? String(index);
}
function renderToolbarButton(action, index, iconOnly = false) {
	if (action.visible === false) return null;
	const icon = normalizeIcon(action.icon);
	const variant = action.type === "danger" ? "danger" : action.type === "primary" ? "primary" : "secondary";
	if (iconOnly) return /* @__PURE__ */ jsx(IconButton, {
		className: "native-datagrid__toolbar-icon-action",
		icon: icon ? `ph ${icon}` : "ph ph-circle",
		label: action.hint ?? action.text ?? "",
		onClick: action.onClick,
		disabled: action.disabled
	}, getToolbarKey(action, index));
	return /* @__PURE__ */ jsx(Button, {
		variant,
		size: "sm",
		icon: icon ? `ph ${icon}` : void 0,
		onClick: action.onClick,
		disabled: action.disabled,
		title: action.hint,
		"aria-label": action.hint ?? action.text,
		children: action.text && /* @__PURE__ */ jsx("span", { children: action.text })
	}, getToolbarKey(action, index));
}
function isToolbarActionVisible(action, permissions) {
	if (action.visible === false) return false;
	if (!action.permission) return true;
	return permissions.includes(action.permission);
}
function renderSelectionActionItem(action, index, disabled) {
	const icon = normalizeIcon(action.icon);
	return /* @__PURE__ */ jsxs("button", {
		type: "button",
		className: `native-datagrid__actions-item${action.type === "danger" ? " native-datagrid__actions-item--danger" : ""}`,
		onClick: action.onClick,
		disabled: disabled || action.disabled,
		role: "menuitem",
		children: [icon && /* @__PURE__ */ jsx("i", {
			className: `ph ${icon}`,
			"aria-hidden": "true"
		}), /* @__PURE__ */ jsx("span", { children: action.text })]
	}, getToolbarKey(action, index));
}
function getResolvedRowActions(row, rowActions) {
	if (!rowActions) return [];
	return typeof rowActions === "function" ? rowActions(row) : rowActions;
}
function renderRowActionItem(action, index, permissions) {
	if (!isToolbarActionVisible(action, permissions)) return null;
	return renderSelectionActionItem(action, index, false);
}
const DROPDOWN_OPENED_EVENT = "lookup:opened";
function SelectionActionsMenu({ actionsLabel, selectedCount, actions = [], permissions }) {
	const [open, setOpen] = useState(false);
	const containerRef = useRef(null);
	const idRef = useRef(Symbol());
	const visibleActions = actions.filter((action) => isToolbarActionVisible(action, permissions));
	const disabled = selectedCount === 0;
	useEffect(() => {
		if (!open) return;
		const handler = (e) => {
			if (!containerRef.current?.contains(e.target)) setOpen(false);
		};
		document.addEventListener("mousedown", handler);
		return () => document.removeEventListener("mousedown", handler);
	}, [open]);
	useEffect(() => {
		const id = idRef.current;
		const handler = (e) => {
			if (e.detail.id !== id) setOpen(false);
		};
		document.addEventListener(DROPDOWN_OPENED_EVENT, handler);
		return () => document.removeEventListener(DROPDOWN_OPENED_EVENT, handler);
	}, []);
	if (visibleActions.length === 0) return null;
	const dangerActions = visibleActions.filter((action) => action.type === "danger");
	const groups = visibleActions.filter((action) => action.type !== "danger").reduce((acc, action) => {
		const group = action.group ?? "default";
		acc[group] = acc[group] ?? [];
		acc[group].push(action);
		return acc;
	}, {});
	const label = selectedCount > 0 ? `${actionsLabel} (${selectedCount})` : actionsLabel;
	const handleToggle = () => {
		if (disabled) return;
		const next = !open;
		if (next) document.dispatchEvent(new CustomEvent(DROPDOWN_OPENED_EVENT, { detail: { id: idRef.current } }));
		setOpen(next);
	};
	return /* @__PURE__ */ jsxs("div", {
		ref: containerRef,
		className: "native-datagrid__actions-menu",
		children: [/* @__PURE__ */ jsxs(Button, {
			variant: "secondary",
			size: "sm",
			"aria-disabled": disabled,
			"aria-expanded": open,
			onClick: handleToggle,
			children: [/* @__PURE__ */ jsx("span", { children: label }), /* @__PURE__ */ jsx("i", {
				className: "ph ph-caret-down",
				"aria-hidden": "true"
			})]
		}), open && !disabled && /* @__PURE__ */ jsxs("div", {
			className: "native-datagrid__actions-popover",
			role: "menu",
			children: [Object.entries(groups).map(([group, items]) => /* @__PURE__ */ jsxs("div", {
				className: "native-datagrid__actions-group",
				children: [items.length > 1 && items[0]?.groupLabel && /* @__PURE__ */ jsx("div", {
					className: "native-datagrid__actions-group-label",
					children: items[0].groupLabel
				}), items.map((action, index) => renderSelectionActionItem(action, index, disabled))]
			}, group)), dangerActions.length > 0 && /* @__PURE__ */ jsx("div", {
				className: "native-datagrid__actions-danger-group",
				children: dangerActions.map((action, index) => renderSelectionActionItem(action, index, disabled))
			})]
		})]
	});
}
function buildToolbar(options, t, onAddClick, onEditClick, onDeleteClick) {
	const actions = [];
	if (options.allowEdit) actions.push({
		text: t("grid.buttonEdit"),
		group: "edit",
		groupLabel: t("grid.buttonEdit"),
		onClick: onEditClick,
		disabled: options.editDisabled
	});
	if (options.allowDelete) actions.push({
		text: t("grid.buttonDelete"),
		type: "danger",
		group: "danger",
		groupLabel: t("grid.buttonDelete"),
		onClick: onDeleteClick,
		disabled: options.deleteDisabled
	});
	return {
		primary: options.toolbar?.primary ?? (options.allowAdd ? [{
			text: t("grid.buttonNew"),
			icon: "ph-plus",
			type: "primary",
			onClick: onAddClick,
			disabled: options.addDisabled
		}] : void 0),
		selection: [...actions, ...options.toolbar?.selection ?? []],
		utility: options.toolbar?.utility,
		showRefresh: options.toolbar?.showRefresh ?? true
	};
}
const BETWEEN_VALUE_SEPARATOR = "|";
function isDateLikeField(field) {
	return field.type === "date" || field.type === "datetime";
}
function getDateStartOfDay(value) {
	return `${value} 00:00:00`;
}
function getDateEndOfDay(value) {
	return `${value} 23:59:59`;
}
function splitBetweenValue(value) {
	const [start = "", end = ""] = value.split(BETWEEN_VALUE_SEPARATOR);
	return [start, end];
}
function joinBetweenValue(start, end) {
	if (!start && !end) return "";
	return `${start}${BETWEEN_VALUE_SEPARATOR}${end}`;
}
function getDefaultFilterOperator(field) {
	if (field.selectedFilterOperation) return field.selectedFilterOperation;
	switch (field.type) {
		case "number":
		case "currency":
		case "date":
		case "datetime":
		case "enum":
		case "select":
		case "switch":
		case "entity": return "=";
		default: return "contains";
	}
}
function getFilterOperators(field) {
	switch (field.type) {
		case "number":
		case "currency": return [
			{
				value: "=",
				label: "="
			},
			{
				value: "<>",
				label: "≠"
			},
			{
				value: ">",
				label: ">"
			},
			{
				value: ">=",
				label: "≥"
			},
			{
				value: "<",
				label: "<"
			},
			{
				value: "<=",
				label: "≤"
			}
		];
		case "date":
		case "datetime": return [
			{
				value: "=",
				label: "="
			},
			{
				value: "<>",
				label: "≠"
			},
			{
				value: ">",
				label: ">"
			},
			{
				value: ">=",
				label: "≥"
			},
			{
				value: "<",
				label: "<"
			},
			{
				value: "<=",
				label: "≤"
			},
			{
				value: "between",
				label: "↔"
			}
		];
		case "enum":
		case "select":
		case "switch":
		case "entity": return [{
			value: "=",
			label: "="
		}];
		default: return [
			{
				value: "contains",
				label: "~"
			},
			{
				value: "notcontains",
				label: "!~"
			},
			{
				value: "startswith",
				label: "^"
			},
			{
				value: "=",
				label: "="
			},
			{
				value: "<>",
				label: "≠"
			}
		];
	}
}
function computeDefaultOperators(fields) {
	const defaults = {};
	fields.forEach((field) => {
		if (field.filterable) defaults[field.name] = getDefaultFilterOperator(field);
	});
	return defaults;
}
function buildFilterExpression(filters, operators, fields) {
	return Object.entries(filters).filter(([, value]) => value.trim() !== "").flatMap(([fieldName, value]) => {
		const field = fields.find((candidate) => candidate.name === fieldName);
		const operator = operators[fieldName] ?? "contains";
		if (field && isDateLikeField(field)) {
			if (operator === "between") {
				const [start, end] = splitBetweenValue(value);
				return [...start ? [[
					fieldName,
					">=",
					field.type === "datetime" ? getDateStartOfDay(start) : start
				]] : [], ...end ? [[
					fieldName,
					"<=",
					field.type === "datetime" ? getDateEndOfDay(end) : end
				]] : []];
			}
			if (field.type === "datetime" && operator === "=") return [[
				fieldName,
				">=",
				getDateStartOfDay(value)
			], [
				fieldName,
				"<=",
				getDateEndOfDay(value)
			]];
		}
		return [[
			fieldName,
			operator,
			value
		]];
	});
}
function FilterCell({ field, operator, remoteOptions, value, onClear, onInputChange, onBetweenInputChange, onSelectChange, onOperatorChange }) {
	const httpClient = useCoreHttpClient();
	if (field.type === "entity") return /* @__PURE__ */ jsx(NativeEntitySelect, {
		className: "native-datagrid__filter-select",
		field,
		httpClient,
		options: remoteOptions,
		value,
		onChange: (nextVal) => onSelectChange(nextVal ? String(nextVal) : "")
	});
	if (field.type === "enum" || field.type === "select" || field.type === "switch") {
		const items = field.data ?? [];
		return /* @__PURE__ */ jsxs("select", {
			className: "native-datagrid__filter-select",
			value,
			"aria-label": `Filtrar ${field.label}`,
			onChange: (e) => onSelectChange(e.target.value),
			children: [/* @__PURE__ */ jsx("option", {
				value: "",
				children: "Todos"
			}), items.map((item) => /* @__PURE__ */ jsx("option", {
				value: String(item["value"] ?? ""),
				children: String(item["text"] ?? item["value"] ?? "")
			}, String(item["value"] ?? "")))]
		});
	}
	const operators = getFilterOperators(field);
	const inputType = field.type === "number" || field.type === "currency" ? "number" : isDateLikeField(field) ? "date" : "text";
	const [betweenStart, betweenEnd] = splitBetweenValue(value);
	const isBetween = isDateLikeField(field) && operator === "between";
	return /* @__PURE__ */ jsxs("div", {
		className: `native-datagrid__filter-wrap${isBetween ? " native-datagrid__filter-wrap--between" : ""}`,
		children: [
			operators.length > 1 ? /* @__PURE__ */ jsx("select", {
				className: "native-datagrid__filter-operator",
				value: operator,
				"aria-label": "Operador de filtro",
				onChange: (e) => onOperatorChange(e.target.value),
				children: operators.map((op) => /* @__PURE__ */ jsx("option", {
					value: op.value,
					children: op.label
				}, op.value))
			}) : /* @__PURE__ */ jsx("i", {
				className: "ph ph-magnifying-glass native-datagrid__filter-icon",
				"aria-hidden": "true"
			}),
			isBetween ? /* @__PURE__ */ jsxs("div", {
				className: "native-datagrid__filter-between-inputs",
				children: [/* @__PURE__ */ jsx("input", {
					type: "date",
					className: "native-datagrid__filter-input",
					value: betweenStart,
					"aria-label": `Filtrar ${field.label} desde`,
					onChange: (e) => onBetweenInputChange(e.target.value, betweenEnd)
				}), /* @__PURE__ */ jsx("input", {
					type: "date",
					className: "native-datagrid__filter-input",
					value: betweenEnd,
					"aria-label": `Filtrar ${field.label} hasta`,
					onChange: (e) => onBetweenInputChange(betweenStart, e.target.value)
				})]
			}) : /* @__PURE__ */ jsx("input", {
				type: inputType,
				className: "native-datagrid__filter-input",
				value,
				"aria-label": `Filtrar ${field.label}`,
				onChange: (e) => onInputChange(e.target.value)
			}),
			/* @__PURE__ */ jsx("button", {
				type: "button",
				className: `native-datagrid__filter-clear${value ? " is-active" : ""}`,
				"aria-label": "Limpiar filtro",
				tabIndex: value ? 0 : -1,
				onClick: onClear,
				children: /* @__PURE__ */ jsx("i", {
					className: "ph ph-x",
					"aria-hidden": "true"
				})
			})
		]
	});
}
function SummaryFooter({ fields, hasCheckbox, hasDetail, hasRowActions, rows, summaryFields }) {
	if (!summaryFields?.length) return null;
	const itemsByColumn = new Map(summaryFields.filter((item) => item.column).map((item) => [item.column, item]));
	const unboundItems = summaryFields.filter((item) => !item.column);
	const fallbackFieldName = fields[fields.length - 1]?.name;
	return /* @__PURE__ */ jsx("tfoot", {
		className: "native-datagrid__summary-footer",
		children: /* @__PURE__ */ jsxs("tr", { children: [
			hasDetail && /* @__PURE__ */ jsx("td", { className: "native-datagrid__detail-cell" }),
			hasCheckbox && /* @__PURE__ */ jsx("td", { className: "native-datagrid__select-cell" }),
			fields.map((field) => {
				const item = itemsByColumn.get(field.name) ?? (field.name === fallbackFieldName ? unboundItems[0] : void 0);
				const align = item?.align ?? field.align;
				return /* @__PURE__ */ jsx("td", {
					style: align ? { textAlign: align } : void 0,
					children: item && /* @__PURE__ */ jsxs("div", {
						className: "native-datagrid__summary-cell",
						style: { justifyContent: align === "center" ? "center" : align === "right" ? "flex-end" : "flex-start" },
						children: [item.label && /* @__PURE__ */ jsx("span", {
							className: "native-datagrid__summary-label",
							children: item.label
						}), /* @__PURE__ */ jsx("span", {
							className: "native-datagrid__summary-value",
							children: resolveSummaryText(rows, item)
						})]
					})
				}, field.name);
			}),
			hasRowActions && /* @__PURE__ */ jsx("td", { className: "native-datagrid__actions-cell" })
		] })
	});
}
function DetailRows({ fields, url }) {
	const httpClient = useCoreHttpClient();
	const resourceStoreFactory = useResourceStoreFactory();
	const { t } = useCoreTranslation();
	const [rows, setRows] = useState([]);
	const [loading, setLoading] = useState(true);
	useEffect(() => {
		const source = resourceStoreFactory({
			url,
			idField: getIdField(fields),
			httpClient
		});
		setLoading(true);
		source.load({}).then((result) => setRows(result.data)).finally(() => setLoading(false));
	}, [
		fields,
		httpClient,
		resourceStoreFactory,
		url
	]);
	if (loading) return /* @__PURE__ */ jsx("div", {
		className: "native-datagrid__loading",
		children: t("grid.loading")
	});
	if (rows.length === 0) return /* @__PURE__ */ jsx("div", {
		className: "native-datagrid__empty",
		children: t("grid.noRecords")
	});
	return /* @__PURE__ */ jsx("div", {
		className: "native-datagrid__detail-content",
		children: /* @__PURE__ */ jsxs("table", {
			className: "native-datagrid__detail-table",
			children: [/* @__PURE__ */ jsx("thead", { children: /* @__PURE__ */ jsx("tr", { children: fields.filter((field) => field.visible && !field.hidden).map((field) => /* @__PURE__ */ jsx("th", { children: field.label }, field.name)) }) }), /* @__PURE__ */ jsx("tbody", { children: rows.map((row, rowIndex) => /* @__PURE__ */ jsx("tr", { children: fields.filter((field) => field.visible && !field.hidden).map((field, columnIndex) => /* @__PURE__ */ jsx("td", { children: renderCell(field, row, rowIndex, columnIndex, void 0, t("common.yes"), t("common.no")) }, field.name)) }, String(row[getIdField(fields)] ?? rowIndex))) })]
		})
	});
}
const NativeDataGridView = forwardRef((options, ref) => {
	const { t } = useCoreTranslation();
	const { notify } = useCoreRuntime();
	const httpClient = useCoreHttpClient();
	const resourceStoreFactory = useResourceStoreFactory();
	const toolbarPermissions = useSmartCrudRoles();
	const navigate = useNavigate();
	const [on, emit] = useEvents();
	const idField = useMemo(() => getIdField(options.fields), [options.fields]);
	const visibleFields = useMemo(() => options.fields.filter((field) => field.visible && !field.hidden).filter((field) => options.visibleColumns == null || options.visibleColumns.includes(field.name)).sort((a, b) => (a.order ?? Number.MAX_SAFE_INTEGER) - (b.order ?? Number.MAX_SAFE_INTEGER)), [options.fields, options.visibleColumns]);
	const [rows, setRows] = useState([]);
	const rowsRef = useRef([]);
	const [totalCount, setTotalCount] = useState(0);
	const [selectedKeys, setSelectedKeys] = useState([]);
	const [filters, setFilters] = useState({});
	const [filterInputs, setFilterInputs] = useState({});
	const filterDebounceRef = useRef(void 0);
	const [filterOperators, setFilterOperators] = useState(() => computeDefaultOperators(options.fields));
	const [filterRemoteOptions, setFilterRemoteOptions] = useState({});
	const loadedFilterOptionsRef = useRef(/* @__PURE__ */ new Set());
	const [sort, setSort] = useState(options.sort ?? []);
	const [pageSize, setPageSize] = useState(options.pageSize ?? 20);
	const [confirmRow, setConfirmRow] = useState(null);
	const [page, setPage] = useState(0);
	const [expandedKeys, setExpandedKeys] = useState(() => /* @__PURE__ */ new Set());
	const [isGridLoading, setIsGridLoading] = useState(!options.manualLoad);
	const [loadingMessage, setLoadingMessage] = useState("");
	const [openRowMenu, setOpenRowMenu] = useState(null);
	const onContentReadyRef = useRef(options.onContentReady);
	const onRowPreparedRef = useRef(options.onRowPrepared);
	const tableWrapRef = useRef(null);
	const [colWidths, setColWidths] = useState({});
	const [tableWidth, setTableWidth] = useState();
	const resizingRef = useRef(null);
	useEffect(() => {
		onContentReadyRef.current = options.onContentReady;
	}, [options.onContentReady]);
	useEffect(() => {
		onRowPreparedRef.current = options.onRowPrepared;
	}, [options.onRowPrepared]);
	const closeRowMenu = useCallback(() => {
		setOpenRowMenu(null);
	}, []);
	useEffect(() => {
		if (!openRowMenu) return;
		const onKeyDown = (e) => {
			if (e.key === "Escape") closeRowMenu();
		};
		const onPointerDown = (e) => {
			if (!e.target.closest(".native-datagrid__row-actions-popover")) closeRowMenu();
		};
		const onScroll = () => {
			closeRowMenu();
		};
		document.addEventListener("keydown", onKeyDown);
		document.addEventListener("pointerdown", onPointerDown);
		const wrap = tableWrapRef.current;
		wrap?.addEventListener("scroll", onScroll, { passive: true });
		return () => {
			document.removeEventListener("keydown", onKeyDown);
			document.removeEventListener("pointerdown", onPointerDown);
			wrap?.removeEventListener("scroll", onScroll);
		};
	}, [openRowMenu, closeRowMenu]);
	useEffect(() => {
		if (openRowMenu) closeRowMenu();
	}, [rows.length]);
	useEffect(() => {
		if (confirmRow && openRowMenu) closeRowMenu();
	}, [
		confirmRow,
		openRowMenu,
		closeRowMenu
	]);
	const source = useMemo(() => resourceStoreFactory({
		url: options.url,
		idField,
		defaultFilterRules: options.filter,
		defaultSortRules: sort,
		httpClient
	}), [
		httpClient,
		idField,
		options.filter,
		options.url,
		resourceStoreFactory,
		sort
	]);
	const loadRows = useCallback(async () => {
		if (options.manualLoad) return rowsRef.current;
		setIsGridLoading(true);
		const loadOptions = {
			filter: buildFilterExpression(filters, filterOperators, options.fields),
			sort
		};
		if (options.paging ?? true) {
			loadOptions.skip = page * pageSize;
			loadOptions.take = pageSize;
		}
		const result = await source.load(loadOptions);
		rowsRef.current = result.data;
		setRows(result.data);
		setTotalCount(result.totalCount);
		setIsGridLoading(false);
		onContentReadyRef.current?.();
		return result.data;
	}, [
		filterOperators,
		filters,
		options.fields,
		options.manualLoad,
		options.paging,
		page,
		pageSize,
		sort,
		source
	]);
	useEffect(() => {
		loadRows();
	}, [loadRows]);
	useEffect(() => {
		rows.forEach((row, rowIndex) => onRowPreparedRef.current?.({
			data: row,
			rowIndex
		}));
	}, [rows]);
	useEffect(() => {
		if (!options.events?.SUCCESS) return;
		const sub = on(options.events.SUCCESS, () => void loadRows());
		return () => sub.unsubscribe();
	}, [
		loadRows,
		on,
		options.events
	]);
	useEffect(() => {
		visibleFields.filter((f) => f.type === "entity" && f.url && !loadedFilterOptionsRef.current.has(f.name)).forEach((f) => {
			loadedFilterOptionsRef.current.add(f.name);
			resourceStoreFactory({
				url: f.url,
				idField: f.valueField,
				httpClient,
				iriMode: true
			}).load({ take: 500 }).then((result) => setFilterRemoteOptions((prev) => ({
				...prev,
				[f.name]: result.data
			}))).catch(() => {});
		});
	}, [
		httpClient,
		resourceStoreFactory,
		visibleFields
	]);
	const handleStateRef = useRef({
		selectedKeys,
		filters,
		filterOperators,
		loadRows,
		idField
	});
	handleStateRef.current = {
		selectedKeys,
		filters,
		filterOperators,
		loadRows,
		idField
	};
	useImperativeHandle(ref, () => ({
		showLoading: (message) => {
			setLoadingMessage(message ?? t("grid.loading"));
			setIsGridLoading(true);
		},
		hideLoading: () => setIsGridLoading(false),
		refresh: () => void handleStateRef.current.loadRows(),
		reset: () => {
			clearTimeout(filterDebounceRef.current);
			setSelectedKeys([]);
			setFilters({});
			setFilterInputs({});
			setFilterOperators(computeDefaultOperators(options.fields));
			setSort([]);
			setPage(0);
			setExpandedKeys(/* @__PURE__ */ new Set());
		},
		loadData: () => Promise.resolve(rowsRef.current),
		getSelectedRowKey: () => {
			const key = handleStateRef.current.selectedKeys[0];
			return typeof key === "string" || typeof key === "number" ? key : void 0;
		},
		getSelectedRow: () => rowsRef.current.find((row) => row[handleStateRef.current.idField] === handleStateRef.current.selectedKeys[0]),
		getSelectedRowKeys: () => handleStateRef.current.selectedKeys,
		getSelectedRows: () => rowsRef.current.filter((row) => handleStateRef.current.selectedKeys.includes(row[handleStateRef.current.idField])),
		getFilter: () => buildFilterExpression(handleStateRef.current.filters, handleStateRef.current.filterOperators, options.fields),
		filter: (filterRule) => {
			const { filterOperators: currentOps } = handleStateRef.current;
			const nextFilters = filterRule ? { [filterRule.field]: String(filterRule.value ?? "") } : {};
			const nextOperators = filterRule ? {
				...currentOps,
				[filterRule.field]: filterRule.operator || getDefaultFilterOperator(options.fields.find((f) => f.name === filterRule.field) ?? options.fields[0])
			} : computeDefaultOperators(options.fields);
			clearTimeout(filterDebounceRef.current);
			setFilters(nextFilters);
			setFilterInputs(nextFilters);
			setFilterOperators(nextOperators);
			setPage(0);
		}
	}), [options.fields, t]);
	const selectedRows = rows.filter((row) => selectedKeys.includes(row[idField]));
	const selectRow = (row) => {
		const key = row[idField];
		const nextKeys = options.selectionMode === "multiple" ? selectedKeys.includes(key) ? selectedKeys.filter((selectedKey) => selectedKey !== key) : [...selectedKeys, key] : [key];
		setSelectedKeys(nextKeys);
		const nextRows = rows.filter((item) => nextKeys.includes(item[idField]));
		options.onSelectionChanged?.({ selectedRowsData: nextRows });
		emit(DATA_GRID_EVENTS.SELECTION_CHANGED, nextRows);
	};
	const onAddClick = () => {
		if (options.onAdd) {
			options.onAdd();
			return;
		}
		if (options.events?.ADD) emit(options.events.ADD);
	};
	const onEditClick = () => {
		const row = selectedRows[0];
		if (row) {
			if (options.onEdit) {
				options.onEdit(row);
				return;
			}
			if (options.events?.EDIT) emit(options.events.EDIT, { row });
		} else notify(t("grid.selectRequired"), "warning", 2e3);
	};
	const onDeleteClick = () => {
		const row = selectedRows[0];
		if (row) setConfirmRow(row);
		else notify(t("grid.selectRequired"), "warning", 2e3);
	};
	const confirmDelete = () => {
		if (confirmRow) {
			if (options.onDelete) options.onDelete(confirmRow);
			else if (options.events?.DELETE) emit(options.events.DELETE, { row: confirmRow });
		}
		setConfirmRow(null);
	};
	const toggleSort = (field) => {
		if (!field.sortable) return;
		setPage(0);
		setSort((current) => {
			const existing = current.find((rule) => rule.selector === field.name);
			if (!existing) return [{
				selector: field.name,
				desc: false
			}];
			if (!existing.desc) return [{
				selector: field.name,
				desc: true
			}];
			return [];
		});
	};
	const handleResizeMouseDown = (fieldName) => (event) => {
		event.preventDefault();
		event.stopPropagation();
		const th = event.currentTarget.closest("th");
		if (!th) return;
		const table = th.closest("table");
		resizingRef.current = {
			name: fieldName,
			startX: event.clientX,
			startWidth: th.offsetWidth,
			startTableWidth: table?.offsetWidth ?? tableWrapRef.current?.clientWidth ?? th.offsetWidth
		};
		const onMouseMove = (moveEvent) => {
			if (!resizingRef.current) return;
			const resize = resizingRef.current;
			const next = Math.max(48, resize.startWidth + (moveEvent.clientX - resize.startX));
			const actualDelta = next - resize.startWidth;
			setColWidths((current) => ({
				...current,
				[resize.name]: next
			}));
			setTableWidth(Math.max(tableWrapRef.current?.clientWidth ?? 0, resize.startTableWidth + actualDelta));
		};
		const onMouseUp = () => {
			resizingRef.current = null;
			window.removeEventListener("mousemove", onMouseMove);
			window.removeEventListener("mouseup", onMouseUp);
		};
		window.addEventListener("mousemove", onMouseMove);
		window.addEventListener("mouseup", onMouseUp);
	};
	const totalPages = Math.max(1, Math.ceil(totalCount / pageSize));
	const hasCheckbox = options.selectionMode === "multiple";
	const hasRowActions = Boolean(options.mode !== "minimal" && (options.inlineActions && (options.allowEdit || options.allowDelete) || options.rowActions));
	const colSpan = visibleFields.length + (hasCheckbox ? 1 : 0) + (options.detailFields ? 1 : 0) + (hasRowActions ? 1 : 0);
	const toolbar = buildToolbar(options, t, onAddClick, onEditClick, onDeleteClick);
	return /* @__PURE__ */ jsx("div", {
		className: "view datagrid-list",
		children: /* @__PURE__ */ jsx("div", {
			className: `view-wrapper ${options.mode === "full" || !options.mode ? "view-wrapper-datagrid-list list-page" : ""}`,
			children: /* @__PURE__ */ jsxs("div", {
				className: "grid theme-dependent native-datagrid",
				children: [
					(options.toolbarVisible ?? true) && options.mode !== "minimal" && /* @__PURE__ */ jsxs("div", {
						className: "native-datagrid__toolbar",
						children: [
							options.hasBackButton && /* @__PURE__ */ jsx(IconButton, {
								icon: "ph ph-arrow-left",
								label: "Volver",
								onClick: () => options.onBack ? options.onBack() : navigate(-1)
							}),
							options.mode === "full" || !options.mode ? /* @__PURE__ */ jsx("div", {
								className: "grid-header",
								children: options.title
							}) : null,
							options.beforeToolbar?.(),
							/* @__PURE__ */ jsx("div", { className: "native-datagrid__toolbar-spacer" }),
							toolbar.primary?.filter((action) => isToolbarActionVisible(action, toolbarPermissions)).map((action, index) => renderToolbarButton(action, index)),
							/* @__PURE__ */ jsx(SelectionActionsMenu, {
								actionsLabel: t("grid.buttonActions"),
								selectedCount: selectedRows.length,
								actions: toolbar.selection,
								permissions: toolbarPermissions
							}),
							toolbar.utility?.filter((action) => isToolbarActionVisible(action, toolbarPermissions)).map((action, index) => renderToolbarButton(action, index, true)),
							toolbar.showRefresh !== false && /* @__PURE__ */ jsx(IconButton, {
								icon: "ph ph-arrow-clockwise",
								label: t("grid.buttonRefresh"),
								onClick: () => void loadRows()
							})
						]
					}),
					/* @__PURE__ */ jsxs("div", {
						className: "native-datagrid__table-wrap",
						ref: tableWrapRef,
						children: [/* @__PURE__ */ jsxs("table", {
							className: "native-datagrid__table",
							style: tableWidth ? { width: tableWidth } : void 0,
							children: [
								/* @__PURE__ */ jsxs("thead", { children: [
									/* @__PURE__ */ jsxs("tr", { children: [
										options.detailFields && /* @__PURE__ */ jsx("th", {
											className: "native-datagrid__detail-cell",
											style: { width: 36 }
										}),
										hasCheckbox && /* @__PURE__ */ jsx("th", { className: "native-datagrid__select-cell" }),
										visibleFields.map((field) => {
											const sortRule = sort.find((rule) => rule.selector === field.name);
											const width = colWidths[field.name] ?? field.width;
											return /* @__PURE__ */ jsxs("th", {
												title: field.label,
												style: {
													width,
													minWidth: field.minWidth,
													textAlign: field.align
												},
												children: [field.sortable ? /* @__PURE__ */ jsxs("button", {
													type: "button",
													className: "native-datagrid__sort-button",
													onClick: () => toggleSort(field),
													children: [/* @__PURE__ */ jsx("span", { children: field.label }), sortRule ? /* @__PURE__ */ jsx("i", {
														className: `ph ${sortRule.desc ? "ph-caret-down" : "ph-caret-up"} native-datagrid__sort-icon`,
														"aria-hidden": "true"
													}) : /* @__PURE__ */ jsx("i", {
														className: "ph ph-caret-up-down native-datagrid__sort-hint",
														"aria-hidden": "true"
													})]
												}) : /* @__PURE__ */ jsx("span", {
													className: "native-datagrid__column-label",
													children: field.label
												}), /* @__PURE__ */ jsx("span", {
													className: "native-datagrid__col-resize",
													onMouseDown: handleResizeMouseDown(field.name),
													onClick: (event) => event.stopPropagation(),
													title: "Arrastrar para redimensionar"
												})]
											}, field.name);
										}),
										hasRowActions && /* @__PURE__ */ jsx("th", { className: "native-datagrid__actions-cell" })
									] }),
									(options.filterRow ?? true) && /* @__PURE__ */ jsxs("tr", {
										className: "native-datagrid__filter-row",
										children: [
											options.detailFields && /* @__PURE__ */ jsx("td", {}),
											hasCheckbox && /* @__PURE__ */ jsx("td", { className: "native-datagrid__select-cell" }),
											visibleFields.map((field) => {
												const applyImmediate = (nextInputs) => {
													clearTimeout(filterDebounceRef.current);
													setFilterInputs(nextInputs);
													setFilters(nextInputs);
													setPage(0);
													options.onFilterChange?.(nextInputs);
												};
												return /* @__PURE__ */ jsx("td", {
													style: { minWidth: field.minWidth },
													className: field.filterable === false ? "native-datagrid__filter-cell--noop" : void 0,
													children: field.filterable ? /* @__PURE__ */ jsx(FilterCell, {
														field,
														operator: filterOperators[field.name] ?? getDefaultFilterOperator(field),
														remoteOptions: filterRemoteOptions[field.name] ?? [],
														value: filterInputs[field.name] ?? "",
														onInputChange: (nextValue) => {
															const nextInputs = {
																...filterInputs,
																[field.name]: nextValue
															};
															if (nextValue === "") delete nextInputs[field.name];
															setFilterInputs(nextInputs);
															clearTimeout(filterDebounceRef.current);
															filterDebounceRef.current = setTimeout(() => {
																setFilters(nextInputs);
																setPage(0);
																options.onFilterChange?.(nextInputs);
															}, 300);
														},
														onBetweenInputChange: (start, end) => {
															const nextValue = joinBetweenValue(start, end);
															const nextInputs = {
																...filterInputs,
																[field.name]: nextValue
															};
															if (nextValue === "") delete nextInputs[field.name];
															applyImmediate(nextInputs);
														},
														onSelectChange: (nextValue) => {
															const nextInputs = {
																...filterInputs,
																[field.name]: nextValue
															};
															if (nextValue === "") delete nextInputs[field.name];
															applyImmediate(nextInputs);
														},
														onOperatorChange: (nextOperator) => {
															setFilterOperators((prev) => ({
																...prev,
																[field.name]: nextOperator
															}));
															const currentValue = filterInputs[field.name];
															if (currentValue) {
																const nextInputs = { ...filterInputs };
																if (nextOperator === "between" && !currentValue.includes(BETWEEN_VALUE_SEPARATOR)) nextInputs[field.name] = joinBetweenValue(currentValue, "");
																if (nextOperator !== "between" && currentValue.includes(BETWEEN_VALUE_SEPARATOR)) nextInputs[field.name] = splitBetweenValue(currentValue).find(Boolean) ?? "";
																applyImmediate(nextInputs);
															}
														},
														onClear: () => {
															const nextInputs = { ...filterInputs };
															delete nextInputs[field.name];
															applyImmediate(nextInputs);
														}
													}) : null
												}, field.name);
											}),
											hasRowActions && /* @__PURE__ */ jsx("td", {})
										]
									}),
									options.errorMessage && /* @__PURE__ */ jsx("tr", {
										className: "native-datagrid__error-row",
										children: /* @__PURE__ */ jsx("td", {
											colSpan,
											children: /* @__PURE__ */ jsxs("div", {
												className: "native-datagrid__error-message",
												role: "alert",
												children: [
													/* @__PURE__ */ jsx("i", {
														className: "ph ph-warning-circle",
														"aria-hidden": "true"
													}),
													/* @__PURE__ */ jsx("span", { children: options.errorMessage }),
													options.onDismissError && /* @__PURE__ */ jsx("button", {
														type: "button",
														className: "native-datagrid__error-close",
														"aria-label": "Cerrar error",
														onClick: options.onDismissError,
														children: /* @__PURE__ */ jsx("i", {
															className: "ph ph-x",
															"aria-hidden": "true"
														})
													})
												]
											})
										})
									})
								] }),
								/* @__PURE__ */ jsx("tbody", { children: rows.length === 0 && isGridLoading ? Array.from({ length: 7 }, (_, i) => /* @__PURE__ */ jsxs("tr", {
									className: "native-datagrid__skeleton-row",
									children: [
										options.detailFields && /* @__PURE__ */ jsx("td", {}),
										hasCheckbox && /* @__PURE__ */ jsx("td", {
											className: "native-datagrid__select-cell",
											children: /* @__PURE__ */ jsx("div", {
												className: "native-datagrid__skeleton-cell",
												style: {
													width: 16,
													height: 16
												}
											})
										}),
										visibleFields.map((field) => /* @__PURE__ */ jsx("td", { children: /* @__PURE__ */ jsx("div", { className: "native-datagrid__skeleton-cell" }) }, field.name)),
										hasRowActions && /* @__PURE__ */ jsx("td", {})
									]
								}, `skeleton-${i}`)) : rows.length === 0 ? /* @__PURE__ */ jsx("tr", { children: /* @__PURE__ */ jsx("td", {
									colSpan,
									className: "native-datagrid__empty-td"
								}) }) : rows.map((row, rowIndex) => {
									const key = row[idField] ?? rowIndex;
									const detailFields = typeof options.detailFields === "function" ? options.detailFields(row) : options.detailFields;
									const detailUrl = options.detailUrl?.replace("{id}", String(key));
									const expanded = expandedKeys.has(key);
									const rowActions = [
										...options.inlineActions && options.allowEdit && (options.onEdit || options.events?.EDIT) ? [{
											text: t("grid.buttonEdit"),
											icon: "ph-pencil-simple",
											onClick: () => {
												if (options.onEdit) options.onEdit(row);
												else emit(options.events.EDIT, { row });
											}
										}] : [],
										...getResolvedRowActions(row, options.rowActions),
										...options.inlineActions && options.allowDelete && (options.onDelete || options.events?.DELETE) ? [{
											text: t("grid.buttonDelete"),
											icon: "ph-trash",
											type: "danger",
											onClick: () => setConfirmRow(row)
										}] : []
									];
									return /* @__PURE__ */ jsxs(React.Fragment, { children: [/* @__PURE__ */ jsxs("tr", {
										className: `native-datagrid__row ${expanded ? "native-datagrid__row--expanded" : ""} ${selectedKeys.includes(key) ? "native-datagrid__row--selected" : ""}`,
										tabIndex: 0,
										onClick: () => selectRow(row),
										onKeyDown: (event) => {
											if (event.key === "Enter" && options.allowEdit && (options.onEdit || options.events?.EDIT)) if (options.onEdit) options.onEdit(row);
											else emit(options.events.EDIT, { row });
											else if (event.key === "Delete" && options.allowDelete && (options.onDelete || options.events?.DELETE)) setConfirmRow(row);
											else if (event.key === "ArrowUp" || event.key === "ArrowDown") {
												event.preventDefault();
												const allRows = event.currentTarget.closest("tbody")?.querySelectorAll("tr.native-datagrid__row");
												if (!allRows) return;
												const idx = Array.from(allRows).indexOf(event.currentTarget);
												const next = allRows[event.key === "ArrowUp" ? idx - 1 : idx + 1];
												if (next) {
													next.focus();
													selectRow(rows[event.key === "ArrowUp" ? idx - 1 : idx + 1]);
												}
											}
										},
										children: [
											options.detailFields && /* @__PURE__ */ jsx("td", {
												className: "native-datagrid__detail-cell",
												children: detailUrl && detailFields && /* @__PURE__ */ jsx("button", {
													type: "button",
													className: `native-datagrid__expand-button${expanded ? " is-expanded" : ""}`,
													"aria-label": expanded ? "Contraer detalle" : "Expandir detalle",
													onClick: (event) => {
														event.stopPropagation();
														setExpandedKeys((current) => {
															const next = new Set(current);
															if (next.has(key)) next.delete(key);
															else next.add(key);
															return next;
														});
													},
													children: /* @__PURE__ */ jsx("i", {
														className: `ph ${expanded ? "ph-caret-down" : "ph-caret-right"}`,
														"aria-hidden": "true"
													})
												})
											}),
											hasCheckbox && /* @__PURE__ */ jsx("td", {
												className: "native-datagrid__select-cell",
												children: /* @__PURE__ */ jsx("input", {
													type: "checkbox",
													checked: selectedKeys.includes(key),
													readOnly: true,
													"aria-label": "Seleccionar fila"
												})
											}),
											visibleFields.map((field, columnIndex) => /* @__PURE__ */ jsx("td", {
												style: { textAlign: field.align },
												title: getCellText(field, row, filterRemoteOptions[field.name], t("common.yes"), t("common.no")),
												children: renderCell(field, row, rowIndex, columnIndex, filterRemoteOptions[field.name], t("common.yes"), t("common.no"))
											}, field.name)),
											hasRowActions && /* @__PURE__ */ jsx("td", {
												className: "native-datagrid__actions-cell",
												onClick: (e) => e.stopPropagation(),
												children: /* @__PURE__ */ jsx("div", {
													className: "native-datagrid__row-actions",
													children: rowActions.length > 0 && /* @__PURE__ */ jsx(IconButton, {
														icon: "ph ph-dots-three-vertical",
														label: t("grid.buttonActions"),
														onClick: (e) => {
															e.stopPropagation();
															const rect = e.currentTarget.getBoundingClientRect();
															const rowKey = key;
															const actionsAtOpen = [
																...options.inlineActions && options.allowEdit && (options.onEdit || options.events?.EDIT) ? [{
																	text: t("grid.buttonEdit"),
																	icon: "ph-pencil-simple",
																	onClick: () => {
																		if (options.onEdit) options.onEdit(row);
																		else emit(options.events.EDIT, { row });
																	}
																}] : [],
																...getResolvedRowActions(row, options.rowActions),
																...options.inlineActions && options.allowDelete && (options.onDelete || options.events?.DELETE) ? [{
																	text: t("grid.buttonDelete"),
																	icon: "ph-trash",
																	type: "danger",
																	onClick: () => setConfirmRow(row)
																}] : []
															].filter((a) => isToolbarActionVisible(a, toolbarPermissions));
															setOpenRowMenu((current) => current && current.key === rowKey ? null : {
																key: rowKey,
																rect,
																actions: actionsAtOpen
															});
														}
													})
												})
											})
										]
									}), expanded && detailUrl && detailFields && /* @__PURE__ */ jsx("tr", {
										className: "native-datagrid__detail-row",
										children: /* @__PURE__ */ jsx("td", {
											colSpan,
											children: /* @__PURE__ */ jsx(DetailRows, {
												fields: detailFields,
												url: detailUrl
											})
										})
									})] }, String(key));
								}) }),
								/* @__PURE__ */ jsx(SummaryFooter, {
									fields: visibleFields,
									hasCheckbox,
									hasDetail: Boolean(options.detailFields),
									hasRowActions,
									rows,
									summaryFields: options.summaryFields
								})
							]
						}), rows.length === 0 && !isGridLoading && /* @__PURE__ */ jsxs("div", {
							className: "native-datagrid__empty",
							"aria-live": "polite",
							children: [/* @__PURE__ */ jsx("i", {
								className: "ph ph-database",
								"aria-hidden": "true"
							}), /* @__PURE__ */ jsx("span", { children: t("grid.noRecords") })]
						})]
					}),
					(options.paging ?? true) && /* @__PURE__ */ jsxs("div", {
						className: "native-datagrid__pager",
						children: [
							/* @__PURE__ */ jsx("span", {
								className: "native-datagrid__pager-info",
								children: totalCount > 0 ? `${page * pageSize + 1}–${Math.min((page + 1) * pageSize, totalCount)} de ${totalCount}` : "0 registros"
							}),
							/* @__PURE__ */ jsxs("div", {
								className: "native-datagrid__pager-nav",
								children: [
									/* @__PURE__ */ jsx("button", {
										type: "button",
										className: "native-datagrid__pager-btn",
										onClick: () => setPage(0),
										disabled: page === 0,
										"aria-label": "Primera página",
										children: /* @__PURE__ */ jsx("i", {
											className: "ph ph-caret-double-left",
											"aria-hidden": "true"
										})
									}),
									/* @__PURE__ */ jsx("button", {
										type: "button",
										className: "native-datagrid__pager-btn",
										onClick: () => setPage((p) => Math.max(0, p - 1)),
										disabled: page === 0,
										"aria-label": "Página anterior",
										children: /* @__PURE__ */ jsx("i", {
											className: "ph ph-caret-left",
											"aria-hidden": "true"
										})
									}),
									getPageRange(page, totalPages).map((pageNum, i) => pageNum === null ? /* @__PURE__ */ jsx("span", {
										className: "native-datagrid__pager-ellipsis",
										children: "…"
									}, `ellipsis-${i}`) : /* @__PURE__ */ jsx("button", {
										type: "button",
										className: `native-datagrid__pager-btn${pageNum === page ? " is-current" : ""}`,
										onClick: () => setPage(pageNum),
										"aria-current": pageNum === page ? "page" : void 0,
										children: pageNum + 1
									}, pageNum)),
									/* @__PURE__ */ jsx("button", {
										type: "button",
										className: "native-datagrid__pager-btn",
										onClick: () => setPage((p) => Math.min(totalPages - 1, p + 1)),
										disabled: page + 1 >= totalPages,
										"aria-label": "Página siguiente",
										children: /* @__PURE__ */ jsx("i", {
											className: "ph ph-caret-right",
											"aria-hidden": "true"
										})
									}),
									/* @__PURE__ */ jsx("button", {
										type: "button",
										className: "native-datagrid__pager-btn",
										onClick: () => setPage(totalPages - 1),
										disabled: page + 1 >= totalPages,
										"aria-label": "Última página",
										children: /* @__PURE__ */ jsx("i", {
											className: "ph ph-caret-double-right",
											"aria-hidden": "true"
										})
									})
								]
							}),
							/* @__PURE__ */ jsxs("div", {
								className: "native-datagrid__pager-size",
								children: [/* @__PURE__ */ jsx("label", {
									className: "native-datagrid__pager-size-label",
									htmlFor: "native-datagrid-page-size",
									children: t("grid.rowsPerPage")
								}), /* @__PURE__ */ jsx("select", {
									id: "native-datagrid-page-size",
									className: "native-datagrid__pager-size-select",
									value: pageSize,
									onChange: (e) => {
										setPageSize(Number(e.target.value));
										setPage(0);
									},
									children: PAGE_SIZE_OPTIONS.map((n) => /* @__PURE__ */ jsx("option", {
										value: n,
										children: n
									}, n))
								})]
							})
						]
					}),
					confirmRow && /* @__PURE__ */ jsx("div", {
						className: "native-datagrid__confirm-overlay",
						role: "alertdialog",
						"aria-modal": "true",
						children: /* @__PURE__ */ jsxs("div", {
							className: "native-datagrid__confirm-card",
							children: [
								/* @__PURE__ */ jsx("p", {
									className: "native-datagrid__confirm-title",
									children: t("dialog.confirmDeleteTitle")
								}),
								/* @__PURE__ */ jsx("p", {
									className: "native-datagrid__confirm-body",
									children: t("dialog.confirmDelete")
								}),
								/* @__PURE__ */ jsxs("div", {
									className: "native-datagrid__confirm-actions",
									children: [/* @__PURE__ */ jsx(Button, {
										variant: "secondary",
										size: "sm",
										onClick: () => setConfirmRow(null),
										children: t("dialog.buttonCancel")
									}), /* @__PURE__ */ jsx(Button, {
										variant: "danger",
										size: "sm",
										onClick: confirmDelete,
										children: t("grid.buttonDelete")
									})]
								})
							]
						})
					}),
					isGridLoading && rows.length > 0 && /* @__PURE__ */ jsx("div", {
						className: "native-datagrid__loading-overlay",
						"aria-live": "polite",
						children: /* @__PURE__ */ jsx("div", {
							className: "native-datagrid__loading-card",
							children: loadingMessage || t("grid.loading")
						})
					}),
					openRowMenu && createPortal(/* @__PURE__ */ jsx("div", {
						className: "native-datagrid__row-actions-popover",
						role: "menu",
						style: {
							position: "fixed",
							right: `${Math.max(0, window.innerWidth - openRowMenu.rect.right + 4)}px`,
							top: (() => {
								const menuHeightEstimate = 180;
								if (openRowMenu.rect.bottom + menuHeightEstimate > window.innerHeight) return `${openRowMenu.rect.top - menuHeightEstimate - 8}px`;
								return `${openRowMenu.rect.bottom + 4}px`;
							})(),
							zIndex: 9999
						},
						onClick: (e) => e.stopPropagation(),
						children: (() => {
							const regular = openRowMenu.actions.filter((a) => a.type !== "danger");
							const danger = openRowMenu.actions.filter((a) => a.type === "danger");
							return /* @__PURE__ */ jsxs(Fragment, { children: [regular.map((action, idx) => renderRowActionItem(action, idx, toolbarPermissions)), danger.length > 0 && /* @__PURE__ */ jsx("div", {
								className: "native-datagrid__actions-danger-group",
								children: danger.map((action, idx) => renderRowActionItem(action, idx, toolbarPermissions))
							})] });
						})()
					}), document.body)
				]
			})
		})
	});
});
NativeDataGridView.displayName = "NativeDataGridView";
//#endregion
//#region assets/crud/dialog/CrudDialogView.tsx
const getIsXSmall = () => typeof window !== "undefined" && window.matchMedia("(max-width: 575.98px)").matches;
function useIsXSmall() {
	const [isXSmall, setIsXSmall] = useState(getIsXSmall);
	useEffect(() => {
		if (typeof window === "undefined") return;
		const media = window.matchMedia("(max-width: 575.98px)");
		const onChange = () => setIsXSmall(media.matches);
		media.addEventListener("change", onChange);
		onChange();
		return () => media.removeEventListener("change", onChange);
	}, []);
	return isXSmall;
}
const CrudDialogView = forwardRef((options, ref) => {
	const { t } = useCoreTranslation();
	const [title, setTitle] = useState(options.title ?? t("crudPage.dialogTitleAdd"));
	const isControlled = options.isOpen !== void 0;
	const [internalVisible, setInternalVisible] = useState(options.visible ?? false);
	const visible = isControlled ? options.isOpen ?? false : internalVisible;
	const [isProcessing, setIsProcessing] = useState(false);
	const isXSmall = useIsXSmall();
	const [on, emit] = useEvents();
	const width = options.width ?? 480;
	useEffect(() => {
		if (options.title) setTitle(options.title);
	}, [options.title]);
	const open = (nextTitle) => {
		if (!isControlled) setInternalVisible(true);
		if (nextTitle) setTitle(nextTitle);
	};
	const close = () => {
		if (isProcessing) return;
		if (!isControlled) setInternalVisible(false);
	};
	const handleClose = () => {
		if (isProcessing) return;
		close();
		options.onClose?.();
	};
	const onCancelClick = () => {
		handleClose();
		if (options.onCancel) {
			options.onCancel();
			return;
		}
		if (options.events?.CANCEL) emit(options.events.CANCEL);
	};
	const onSaveClick = () => {
		if (options.onSave) {
			options.onSave();
			return;
		}
		if (options.events?.SAVE) emit(options.events.SAVE);
	};
	const setupObservers = () => {
		if (options.events?.ADD) on(options.events.ADD, (payload) => open(payload?.title ?? t("crudPage.dialogTitleAdd")));
		if (options.events?.EDIT) on(options.events.EDIT, () => open(t("crudPage.dialogTitleEdit")));
		if (options.events?.LOADING) on(options.events.LOADING, (isLoading) => setIsProcessing(isLoading));
		if (options.events?.SUCCESS) on(options.events.SUCCESS, () => close());
	};
	const setupObserversRef = useRef(setupObservers);
	setupObserversRef.current = setupObservers;
	useEffect(() => {
		setupObserversRef.current();
	}, []);
	useImperativeHandle(ref, () => ({
		open,
		close
	}));
	return /* @__PURE__ */ jsxs(AppDialog, {
		title,
		open: visible,
		fullScreen: isXSmall,
		width,
		height: options.height ?? "auto",
		maxWidth: options.width ?? void 0,
		onClose: handleClose,
		closeOnEscape: !isProcessing,
		closeOnOutsideClick: false,
		keepMounted: true,
		rootClassName: "crud-dialog-root",
		className: ["crud-dialog", options.fitContent ? "fit-content" : "form-popup"].join(" "),
		headerClassName: "crud-dialog__header",
		titleClassName: "crud-dialog__title",
		bodyClassName: "crud-dialog__body",
		footerClassName: "crud-dialog__footer",
		footer: options.footerVisible ?? true ? /* @__PURE__ */ jsxs("div", {
			className: `form-popup-buttons-container ${width <= 360 ? "flex-buttons" : ""}`,
			children: [/* @__PURE__ */ jsx(Button, {
				variant: "secondary",
				onClick: onCancelClick,
				disabled: isProcessing,
				children: options.negativeText ?? t("dialog.buttonCancel")
			}), /* @__PURE__ */ jsx(Button, {
				variant: "primary",
				className: "dialog-save-button",
				onClick: onSaveClick,
				loading: isProcessing,
				children: /* @__PURE__ */ jsx("span", { children: options.positiveText ?? t("dialog.buttonSave") })
			})]
		}) : void 0,
		children: [options.children, isProcessing && /* @__PURE__ */ jsx("div", {
			className: "crud-dialog__loading",
			"aria-hidden": "true",
			children: /* @__PURE__ */ jsx("span", { className: "crud-dialog__loading-indicator" })
		})]
	});
});
CrudDialogView.displayName = "CrudDialogView";
//#endregion
//#region assets/crud/crud/usePermissions.ts
/** Resolve a single permission entry: callable → call it, boolean → use it, undefined → fallback. */
function resolve(value, fallback) {
	if (value === void 0) return fallback;
	if (typeof value === "function") return value();
	return value;
}
/**
* Derive permissions from `hydra:supportedOperation` method list.
* Returns `undefined` for each permission when `supportedOperations` is empty
* (signals "no info available — use the next fallback level").
*/
function fromOperations(supportedOperations) {
	if (supportedOperations.length === 0) return void 0;
	return {
		canAdd: supportedOperations.includes("POST"),
		canEdit: supportedOperations.includes("PUT") || supportedOperations.includes("PATCH"),
		canDelete: supportedOperations.includes("DELETE")
	};
}
/**
* Resolves RBAC permissions for a resource with the following precedence
* (highest → lowest):
*   1. `resource.permissions.canX` — callable or boolean override
*   2. inferred from `supportedOperations` (hydra:supportedOperation methods)
*   3. platform defaults            — canAdd/Edit/Delete=true, canExport/BulkDelete=false
*
* Memoized on `resource.id` — stable reference across re-renders.
*/
function usePermissions(resource, supportedOperations = []) {
	const opsKey = supportedOperations.slice().sort().join(",");
	return useMemo(() => {
		const p = resource.permissions;
		const inferred = fromOperations(supportedOperations);
		function resolveWithInferred(permValue, inferredValue, platformDefault) {
			if (permValue !== void 0) return resolve(permValue, platformDefault);
			if (inferredValue !== void 0) return inferredValue;
			return platformDefault;
		}
		return {
			canAdd: resolveWithInferred(p?.canAdd, inferred?.canAdd, true),
			canEdit: resolveWithInferred(p?.canEdit, inferred?.canEdit, true),
			canDelete: resolveWithInferred(p?.canDelete, inferred?.canDelete, true),
			canExport: resolve(p?.canExport, false),
			canBulkDelete: resolve(p?.canBulkDelete, false)
		};
	}, [resource.id, opsKey]);
}
//#endregion
//#region assets/crud/crud/useSelectionState.ts
function useSelectionState(identityField) {
	const [selectedIds, setSelectedIds] = useState([]);
	const onSelectionChanged = useCallback((selectedRows) => {
		setSelectedIds(selectedRows.map((row) => {
			const val = row[identityField];
			if (typeof val === "string" || typeof val === "number") return val;
			return String(val);
		}));
	}, [identityField]);
	const clearSelection = useCallback(() => {
		setSelectedIds([]);
	}, []);
	return {
		selectedIds,
		selectedCount: selectedIds.length,
		hasSelection: selectedIds.length > 0,
		clearSelection,
		onSelectionChanged
	};
}
//#endregion
//#region assets/crud/crud/useColumnPreset.ts
function readFromStorage(resourceId) {
	try {
		return localStorage.getItem(`column-preset:${resourceId}`);
	} catch {
		return null;
	}
}
function writeToStorage(resourceId, key) {
	try {
		if (key === null) localStorage.removeItem(`column-preset:${resourceId}`);
		else localStorage.setItem(`column-preset:${resourceId}`, key);
	} catch {}
}
function resolveVisibleColumns(resource, activeKey) {
	if (activeKey === null || !resource.columnPresets?.length) return null;
	return resource.columnPresets.find((p) => p.key === activeKey)?.columns ?? null;
}
function useColumnPreset(resource) {
	const [activePreset, setActivePresetState] = useState(() => {
		if (!resource.columnPresets?.length) return null;
		const stored = readFromStorage(resource.id);
		if (stored && resource.columnPresets.some((p) => p.key === stored)) return stored;
		return resource.defaultPreset ?? null;
	});
	return {
		activePreset,
		setPreset: useCallback((key) => {
			writeToStorage(resource.id, key);
			setActivePresetState(key);
		}, [resource.id]),
		visibleColumns: resolveVisibleColumns(resource, activePreset)
	};
}
//#endregion
//#region assets/crud/crud/resolveCrudResource.ts
function resolveCrudResource(resource, overrides = {}) {
	const events = resource.events ?? createScopedEventBus(resource.id);
	return {
		...resource,
		...overrides,
		events
	};
}
//#endregion
//#region assets/crud/crud/useCrudPage.ts
function useCrudPage(resource, externalFormRef) {
	const resolvedResource = useMemo(() => resolveCrudResource(resource), [resource]);
	const events = resolvedResource.events;
	const _internalFormRef = useRef(null);
	const formRef = externalFormRef ?? _internalFormRef;
	const fields = useMemo(() => resolvedResource.fields ?? [], [resolvedResource.fields]);
	return {
		events,
		resource: resolvedResource,
		fields,
		formRef,
		permissions: usePermissions(resolvedResource, resolvedResource._supportedOperations ?? []),
		selectionState: useSelectionState(useMemo(() => {
			return fields.find((f) => f.isIdentity)?.name ?? "id";
		}, [fields])),
		presetState: useColumnPreset(resolvedResource)
	};
}
//#endregion
//#region assets/crud/crud/AuditTrailPanel.tsx
const ACTION_COLORS = {
	create: "#2e7d32",
	update: "#1565c0",
	delete: "#c62828"
};
function DefaultEntryRenderer({ entry }) {
	const { t } = useCoreTranslation();
	const date = new Date(entry.timestamp);
	const formatted = Number.isNaN(date.getTime()) ? entry.timestamp : date.toLocaleString(getCoreLocale(), { timeZone: getCoreTimezone() });
	const actionLabels = {
		create: t("auditTrail.action.create"),
		update: t("auditTrail.action.update"),
		delete: t("auditTrail.action.delete")
	};
	const changeKeys = Object.keys(entry.changes);
	return /* @__PURE__ */ jsxs("li", {
		style: {
			borderBottom: "1px solid #e0e0e0",
			padding: "10px 0",
			listStyle: "none"
		},
		children: [/* @__PURE__ */ jsxs("div", {
			style: {
				display: "flex",
				alignItems: "center",
				gap: 8,
				marginBottom: 4
			},
			children: [
				/* @__PURE__ */ jsx("span", {
					style: {
						fontSize: 12,
						color: "#757575"
					},
					children: formatted
				}),
				/* @__PURE__ */ jsx("span", {
					style: {
						fontSize: 12,
						color: "#424242"
					},
					children: entry.user
				}),
				/* @__PURE__ */ jsx("span", {
					style: {
						fontSize: 11,
						fontWeight: 600,
						padding: "1px 6px",
						borderRadius: 4,
						backgroundColor: ACTION_COLORS[entry.action],
						color: "#fff"
					},
					children: actionLabels[entry.action]
				})
			]
		}), changeKeys.length > 0 && /* @__PURE__ */ jsx("ul", {
			style: {
				margin: "4px 0 0 0",
				padding: "0 0 0 16px"
			},
			children: changeKeys.map((field) => {
				const { before, after } = entry.changes[field];
				return /* @__PURE__ */ jsxs("li", {
					style: {
						fontSize: 12,
						color: "#616161"
					},
					children: [
						/* @__PURE__ */ jsxs("strong", { children: [field, ":"] }),
						" ",
						/* @__PURE__ */ jsx("span", {
							style: { color: "#c62828" },
							children: String(before ?? "—")
						}),
						" → ",
						/* @__PURE__ */ jsx("span", {
							style: { color: "#2e7d32" },
							children: String(after ?? "—")
						})
					]
				}, field);
			})
		})]
	});
}
function AuditTrailPanel({ url, renderEntry, visible, onClose }) {
	const { t } = useCoreTranslation();
	const httpClient = useCoreHttpClient();
	const [fetchState, setFetchState] = useState({ status: "idle" });
	useEffect(() => {
		if (!visible || url === null) {
			setFetchState({ status: "idle" });
			return;
		}
		let cancelled = false;
		setFetchState({ status: "loading" });
		httpClient.get(url).then((response) => {
			if (!cancelled) setFetchState({
				status: "success",
				entries: response.data
			});
		}).catch(() => {
			if (!cancelled) setFetchState({ status: "error" });
		});
		return () => {
			cancelled = true;
		};
	}, [
		httpClient,
		url,
		visible
	]);
	if (!visible) return null;
	return /* @__PURE__ */ jsxs("aside", {
		className: "audit-trail-panel",
		style: {
			border: "1px solid #e0e0e0",
			borderRadius: 4,
			padding: "12px 16px",
			backgroundColor: "#fafafa",
			minWidth: 280
		},
		children: [/* @__PURE__ */ jsxs("div", {
			style: {
				display: "flex",
				alignItems: "center",
				justifyContent: "space-between",
				marginBottom: 12
			},
			children: [/* @__PURE__ */ jsx("strong", {
				style: { fontSize: 14 },
				children: t("auditTrail.title")
			}), /* @__PURE__ */ jsx("button", {
				type: "button",
				onClick: onClose,
				"aria-label": t("auditTrail.closeButton"),
				style: {
					background: "none",
					border: "none",
					cursor: "pointer",
					fontSize: 18,
					lineHeight: 1,
					padding: "0 4px",
					color: "#616161"
				},
				children: "×"
			})]
		}), url === null ? /* @__PURE__ */ jsx("p", {
			style: {
				fontSize: 13,
				color: "#757575",
				margin: 0
			},
			children: t("auditTrail.selectRecord")
		}) : fetchState.status === "loading" ? /* @__PURE__ */ jsx("p", {
			style: {
				fontSize: 13,
				color: "#757575",
				margin: 0
			},
			children: t("auditTrail.loading")
		}) : fetchState.status === "error" ? /* @__PURE__ */ jsx("p", {
			style: {
				fontSize: 13,
				color: "#c62828",
				margin: 0
			},
			children: t("auditTrail.error")
		}) : fetchState.status === "success" ? fetchState.entries.length === 0 ? /* @__PURE__ */ jsx("p", {
			style: {
				fontSize: 13,
				color: "#757575",
				margin: 0
			},
			children: t("auditTrail.empty")
		}) : /* @__PURE__ */ jsx("ul", {
			style: {
				margin: 0,
				padding: 0
			},
			children: fetchState.entries.map((entry) => renderEntry ? /* @__PURE__ */ jsx(React.Fragment, { children: renderEntry(entry) }, String(entry.id)) : /* @__PURE__ */ jsx(DefaultEntryRenderer, { entry }, String(entry.id)))
		}) : null]
	});
}
//#endregion
//#region assets/crud/crud/dialogStore.ts
function initialDialogState() {
	return {
		isOpen: false,
		mode: "add",
		rowData: null
	};
}
function dialogReducer(state, action) {
	switch (action.type) {
		case "OPEN": return {
			...state,
			[action.resourceId]: {
				isOpen: true,
				mode: action.mode,
				rowData: action.rowData
			}
		};
		case "CLOSE": {
			const existing = state[action.resourceId] ?? initialDialogState();
			return {
				...state,
				[action.resourceId]: {
					...existing,
					isOpen: false
				}
			};
		}
		default: return state;
	}
}
//#endregion
//#region assets/crud/crud/DialogStoreProvider.tsx
const DialogStoreContext = createContext(null);
function DialogStoreProvider({ children }) {
	const [state, dispatch] = useReducer(dialogReducer, {});
	return /* @__PURE__ */ jsx(DialogStoreContext.Provider, {
		value: {
			state,
			dispatch
		},
		children
	});
}
function useDialogStoreContext() {
	return useContext(DialogStoreContext);
}
//#endregion
//#region assets/crud/crud/useCrudDialogStore.ts
function useCrudDialogStore(resourceId) {
	const { state, dispatch } = useDialogStoreContext();
	const dialogState = state[resourceId] ?? initialDialogState();
	const openDialog = useCallback((mode, rowData = null) => {
		dispatch({
			type: "OPEN",
			resourceId,
			mode,
			rowData
		});
	}, [dispatch, resourceId]);
	const closeDialog = useCallback(() => {
		dispatch({
			type: "CLOSE",
			resourceId
		});
	}, [dispatch, resourceId]);
	return {
		...dialogState,
		openDialog,
		closeDialog
	};
}
//#endregion
//#region assets/crud/crud/resolveResourceDetails.ts
function resolveResourceDetails(resource) {
	return {
		gridDetail: resource.gridDetail,
		formDetail: resource.formDetail
	};
}
//#endregion
//#region assets/crud/crud/resolveResourceToolbar.ts
function resolveResourceToolbar(resource, context) {
	return typeof resource.toolbar === "function" ? resource.toolbar(context) : resource.toolbar;
}
//#endregion
//#region assets/crud/crud/CrudPage.tsx
function inferFilterOperator(field) {
	if (field.selectedFilterOperation) return field.selectedFilterOperation;
	return field.valueType === "string" ? "contains" : "=";
}
function buildRoutingFilterRules(fields, initialFilters) {
	return Object.entries(initialFilters).flatMap(([name, value]) => {
		const field = fields.find((candidate) => candidate.name === name && candidate.filterable !== false);
		if (!field || value === "") return [];
		return [{
			field: name,
			operator: inferFilterOperator(field),
			value
		}];
	});
}
/**
* Public export — wraps CrudPageInner in its own DialogStoreProvider so that
* existing pages do not need to add a provider themselves.
*/
const CrudPage = ({ resource, onFormDataChange, initialRecordId, initialIsNew, computedValues, initialFilters, onFiltersChange, formRef, onSelectionChanged, editDisabled, deleteDisabled, gridRef, onOperationChange }) => /* @__PURE__ */ jsx(DialogStoreProvider, { children: /* @__PURE__ */ jsx(CrudPageInner, {
	resource,
	onFormDataChange,
	initialRecordId,
	initialIsNew,
	computedValues,
	initialFilters,
	onFiltersChange,
	formRef,
	onSelectionChanged,
	editDisabled,
	deleteDisabled,
	gridRef,
	onOperationChange
}) });
const CrudPageInner = ({ resource, onFormDataChange, initialRecordId = null, initialIsNew = false, computedValues = {}, initialFilters = {}, onFiltersChange, formRef: externalFormRef, onSelectionChanged: externalOnSelectionChanged, editDisabled, deleteDisabled, gridRef: externalGridRef, onOperationChange }) => {
	const { t } = useCoreTranslation();
	const { events, resource: resolvedResource, fields, formRef, permissions, selectionState, presetState } = useCrudPage(useMemo(() => resolveCrudResource(resource), [resource]), externalFormRef);
	const datagridFields = useMemo(() => fields.filter((field) => field.isIdentity || field.visible !== false), [fields]);
	const routeAwareGridFields = useMemo(() => datagridFields.map((field) => {
		const routeFilterValue = initialFilters[field.name];
		if (routeFilterValue === void 0) {
			if (field.filterValue === void 0) return field;
			return {
				...field,
				filterValue: void 0
			};
		}
		return {
			...field,
			filterValue: routeFilterValue,
			selectedFilterOperation: inferFilterOperator(field)
		};
	}), [datagridFields, initialFilters]);
	const routeAwareFilters = useMemo(() => [...resolvedResource.filter ?? [], ...buildRoutingFilterRules(routeAwareGridFields, initialFilters)], [
		initialFilters,
		resolvedResource.filter,
		routeAwareGridFields
	]);
	const isInlineEditMode = resolvedResource.editMode === "row" || resolvedResource.editMode === "cell" || resolvedResource.editMode === "batch";
	const hasMultipleSelection = (resolvedResource.bulkActions?.length ?? 0) > 0;
	const hasPresets = (resolvedResource.columnPresets?.length ?? 0) > 0;
	const hasAuditTrail = resolvedResource.auditTrail?.enabled === true;
	const { gridDetail, formDetail } = resolveResourceDetails(resolvedResource);
	const internalGridRef = React.useRef(null);
	const gridRef = externalGridRef ?? internalGridRef;
	const [selectedRows, setSelectedRows] = useState([]);
	const [selectedRowId, setSelectedRowId] = useState(null);
	const [auditOpen, setAuditOpen] = useState(false);
	const [confirmState, setConfirmState] = useState({
		open: false,
		action: null
	});
	const { isOpen: dialogIsOpen, mode: dialogMode, rowData: dialogRowData, openDialog, closeDialog } = useCrudDialogStore(resolvedResource.id);
	const [on, emit] = useEvents();
	const [operationVersion, setOperationVersion] = useState(0);
	const refreshGrid = useCallback(() => {
		gridRef.current?.refresh();
	}, [gridRef]);
	const resetDialogOperation = useCallback(() => {
		closeDialog();
		onOperationChange?.(null);
	}, [closeDialog, onOperationChange]);
	const openAddDialog = useCallback((rowData = null) => {
		setOperationVersion((current) => current + 1);
		openDialog("add", rowData);
		onOperationChange?.("create");
	}, [onOperationChange, openDialog]);
	const openEditDialog = useCallback((row) => {
		setOperationVersion((current) => current + 1);
		openDialog("edit", row);
		onOperationChange?.("edit");
	}, [onOperationChange, openDialog]);
	const routingActionsRef = React.useRef({
		openAddDialog,
		openEditDialog,
		resetDialogOperation
	});
	routingActionsRef.current = {
		openAddDialog,
		openEditDialog,
		resetDialogOperation
	};
	React.useEffect(() => {
		if (initialRecordId != null) {
			routingActionsRef.current.openEditDialog({ id: initialRecordId });
			return;
		}
		if (initialIsNew) {
			routingActionsRef.current.openAddDialog(null);
			return;
		}
		routingActionsRef.current.resetDialogOperation();
	}, [initialIsNew, initialRecordId]);
	const onDetailRowsChangedRef = React.useRef(resolvedResource.onDetailRowsChanged);
	onDetailRowsChangedRef.current = resolvedResource.onDetailRowsChanged;
	React.useEffect(() => {
		if (!onDetailRowsChangedRef.current) return;
		const handler = () => onDetailRowsChangedRef.current?.(formRef);
		const subs = [
			on(FORM_EVENTS.ROW_ADDED, handler),
			on(FORM_EVENTS.ROW_UPDATED, handler),
			on(FORM_EVENTS.ROW_REMOVED, handler)
		];
		return () => subs.forEach((sub) => sub.unsubscribe());
	}, []);
	const dialogTitle = useMemo(() => {
		return {
			add: t("crudPage.dialogTitleAdd"),
			edit: t("crudPage.dialogTitleEdit"),
			view: t("crudPage.dialogTitleView")
		}[dialogMode] ?? t("crudPage.dialogTitleAdd");
	}, [dialogMode, t]);
	/** Bridge between the grid renderer's selection event and our selection state.
	* Also captures the selected row ID for the audit trail. */
	const handleSelectionChanged = (e) => {
		const rows = e.selectedRowsData ?? [];
		setSelectedRows(rows);
		selectionState.onSelectionChanged(rows);
		externalOnSelectionChanged?.(rows);
		if (hasAuditTrail) {
			const first = rows[0];
			if (first != null) setSelectedRowId(first["id"] ?? first["ID"] ?? null);
		}
	};
	const auditUrl = useMemo(() => {
		if (!resolvedResource.auditTrail?.enabled || selectedRowId == null) return null;
		const { apiUrl } = resolvedResource.auditTrail;
		return typeof apiUrl === "function" ? apiUrl(selectedRowId) : `${apiUrl}${selectedRowId}`;
	}, [resolvedResource.auditTrail, selectedRowId]);
	const executeBulkAction = useCallback(async (action) => {
		await action.onAction(selectionState.selectedIds);
		selectionState.clearSelection();
		refreshGrid();
	}, [refreshGrid, selectionState]);
	const handleBulkAction = useCallback((action) => {
		if (action.confirmMessage) setConfirmState({
			open: true,
			action
		});
		else executeBulkAction(action);
	}, [executeBulkAction]);
	const toolbar = useMemo(() => {
		return resolveResourceToolbar(resolvedResource, {
			resource: resolvedResource,
			selectedRow: selectedRows[0],
			selectedRows,
			gridRef,
			formRef,
			events,
			emit
		});
	}, [
		emit,
		events,
		formRef,
		gridRef,
		resolvedResource,
		selectedRows
	]);
	/** Preset selector rendered as a toolbar slot via `beforeToolbar`. */
	const renderPresetSelector = hasPresets ? () => /* @__PURE__ */ jsxs("div", {
		className: "column-preset-selector",
		style: {
			display: "inline-flex",
			alignItems: "center",
			gap: 8
		},
		children: [/* @__PURE__ */ jsx("label", {
			htmlFor: `preset-${resolvedResource.id}`,
			style: { fontSize: 13 },
			children: t("crudPage.columnsLabel")
		}), /* @__PURE__ */ jsxs("select", {
			id: `preset-${resolvedResource.id}`,
			"aria-label": "Seleccionar preset de columnas",
			value: presetState.activePreset ?? "",
			onChange: (e) => presetState.setPreset(e.target.value || null),
			style: { fontSize: 13 },
			children: [/* @__PURE__ */ jsx("option", {
				value: "",
				children: t("crudPage.allColumns")
			}), resolvedResource.columnPresets.map((p) => /* @__PURE__ */ jsx("option", {
				value: p.key,
				children: p.label
			}, p.key))]
		})]
	}) : void 0;
	return /* @__PURE__ */ jsxs("div", {
		id: "wrapper",
		className: auditOpen ? "wrapper--with-audit" : void 0,
		children: [
			(resolvedResource.bulkActions?.length ?? 0) > 0 && selectionState.hasSelection && /* @__PURE__ */ jsxs("div", {
				className: "bulk-toolbar",
				role: "toolbar",
				"aria-label": "Acciones en lote",
				children: [/* @__PURE__ */ jsx("span", {
					className: "bulk-toolbar__count",
					children: t("crudPage.selectedCount", { count: selectionState.selectedCount })
				}), resolvedResource.bulkActions.map((action) => /* @__PURE__ */ jsx(Button, {
					variant: "secondary",
					size: "sm",
					icon: action.icon,
					onClick: () => handleBulkAction(action),
					children: action.label
				}, action.key))]
			}),
			hasAuditTrail && /* @__PURE__ */ jsx("div", {
				className: "audit-trail-toolbar",
				style: { marginBottom: 8 },
				children: /* @__PURE__ */ jsx("button", {
					type: "button",
					onClick: () => setAuditOpen((prev) => !prev),
					"aria-pressed": auditOpen,
					"aria-label": "Mostrar historial de cambios",
					children: t("crudPage.auditTrailButton")
				})
			}),
			/* @__PURE__ */ jsx(NativeDataGridView, {
				ref: gridRef,
				id: resolvedResource.id,
				title: resolvedResource.title,
				url: resolvedResource.apiUrl,
				fields: routeAwareGridFields,
				events,
				allowAdd: permissions.canAdd,
				allowEdit: permissions.canEdit,
				allowDelete: permissions.canDelete,
				allowExport: permissions.canExport,
				editDisabled,
				deleteDisabled,
				sort: resolvedResource.sort,
				filter: routeAwareFilters,
				paging: resolvedResource.paging,
				headerFilter: resolvedResource.headerFilter,
				mode: resolvedResource.mode,
				stateStoringEnabled: resolvedResource.stateStoring,
				toolbar,
				rowActions: resolvedResource.rowActions,
				onAdd: () => openAddDialog(null),
				onEdit: openEditDialog,
				onDelete: (row) => formRef.current?.deleteRow(row),
				selectionMode: hasMultipleSelection ? "multiple" : "single",
				onSelectionChanged: handleSelectionChanged,
				editMode: resolvedResource.editMode,
				visibleColumns: presetState.visibleColumns,
				beforeToolbar: renderPresetSelector,
				detailUrl: gridDetail?.url,
				detailFields: gridDetail?.fields,
				onFilterChange: onFiltersChange,
				adapter: resolvedResource.adapter
			}),
			!isInlineEditMode && /* @__PURE__ */ jsx(CrudDialogView, {
				events,
				width: resolvedResource.dialogWidth,
				height: resolvedResource.dialogHeight,
				title: dialogTitle,
				isOpen: dialogIsOpen,
				onClose: resetDialogOperation,
				onCancel: resetDialogOperation,
				onSave: () => formRef.current?.save(),
				children: /* @__PURE__ */ jsx(NativeFormView, {
					ref: formRef,
					url: resolvedResource.apiUrl,
					fields,
					events,
					detailFields: formDetail?.fields,
					detailUrl: formDetail?.url,
					detailPropertyName: formDetail?.propertyName,
					allowAdding: formDetail?.allowAdding,
					allowDeleting: formDetail?.allowDeleting,
					allowUpdating: formDetail?.allowUpdating,
					requiredDetail: formDetail?.required,
					format: resolvedResource.format,
					onSaveSuccess: (response) => {
						resolvedResource.onSaveSuccess?.(response);
						resetDialogOperation();
						refreshGrid();
					},
					onSaveError: resolvedResource.onSaveError,
					onDeleteSuccess: (response) => {
						resolvedResource.onDeleteSuccess?.(response);
						refreshGrid();
					},
					onDeleteError: resolvedResource.onDeleteError,
					formLayout: resolvedResource.formLayout,
					computedValues,
					onFieldDataChanged: onFormDataChange,
					adapter: resolvedResource.adapter,
					operation: dialogIsOpen && dialogMode !== "view" ? dialogMode : null,
					rowData: dialogRowData,
					operationVersion
				})
			}),
			hasAuditTrail && /* @__PURE__ */ jsx(AuditTrailPanel, {
				url: auditUrl,
				renderEntry: resolvedResource.auditTrail.renderEntry,
				visible: auditOpen,
				onClose: () => setAuditOpen(false)
			}),
			/* @__PURE__ */ jsx(ConfirmDialog, {
				open: confirmState.open,
				title: t("crudPage.confirmActionTitle"),
				message: confirmState.action?.confirmMessage ?? "",
				variant: "warning",
				onConfirm: () => {
					const action = confirmState.action;
					setConfirmState({
						open: false,
						action: null
					});
					if (action) executeBulkAction(action);
				},
				onCancel: () => setConfirmState({
					open: false,
					action: null
				})
			})
		]
	});
};
//#endregion
//#region assets/crud/crud/routing/useRouting.ts
/**
* Reads URL params and exposes helpers for SmartCrudPage to:
* - Open the edit dialog for the record whose id is in the URL
* - Open the add dialog when the URL contains the sentinel value "new"
* - Sync grid filters to/from URL query params
*
* If `routing` is undefined, returns neutral defaults so the feature
* is completely opt-in (no side-effects when routing is not configured).
*/
function useRouting(routing) {
	const params = useParams();
	const [searchParams, setSearchParams] = useSearchParams();
	return {
		initialRecordId: (() => {
			if (!routing?.routeParam) return null;
			const value = params[routing.routeParam];
			if (!value || value === "new") return null;
			return value;
		})(),
		initialIsNew: (() => {
			if (!routing?.routeParam) return false;
			return params[routing.routeParam] === "new";
		})(),
		initialFilters: (() => {
			if (!routing?.syncFiltersToUrl) return {};
			const filters = {};
			searchParams.forEach((value, key) => {
				filters[key] = value;
			});
			return filters;
		})(),
		syncFilters: useCallback((filters) => {
			if (!routing?.syncFiltersToUrl) return;
			const next = new URLSearchParams();
			Object.entries(filters).forEach(([key, value]) => {
				if (value) next.set(key, value);
			});
			setSearchParams(next, { replace: true });
		}, [routing?.syncFiltersToUrl, setSearchParams])
	};
}
//#endregion
//#region assets/crud/crud/devHint.ts
/**
* Returns true when running on a local development hostname.
* Works regardless of Vite build mode (`import.meta.env.DEV`).
*/
function isDevEnvironment() {
	if (typeof window === "undefined") return false;
	const host = window.location.hostname;
	return host === "localhost" || host === "127.0.0.1" || host.endsWith(".localhost") || host.startsWith("192.168.");
}
/**
* Derives a one-line field descriptor string for use in the hint snippet.
* Produces e.g. `{ key: 'name', label: 'Name', type: 'text' }`.
*/
function fieldToHintLine(field) {
	const readOnlyPart = field.readonly ? ", readOnly: true" : "";
	return `    { key: '${field.name}', label: '${field.label}', type: '${field.type}'${readOnlyPart} },`;
}
/**
* Log a dev hint for auto-inferred fields the first time a resource URL is
* mounted with auto-discovery active.
*
* @param resourceUrl - The `apiUrl` passed to SmartCrudPage (normalized form).
* @param fields      - The auto-inferred `Field[]` resolved by useResourceSchema.
* @param operations  - The inferred HTTP methods exposed by the resource.
*/
function logDevHint(resourceUrl, fields, operations = []) {
	if (!isDevEnvironment()) return;
	const fieldLines = fields.filter((f) => !f.isIdentity).map(fieldToHintLine).join("\n");
	const operationsLine = operations.length > 0 ? `  operations: [${operations.join(", ")}],
` : "";
	console.info(`[SmartCRUD] Auto-inferred fields for "${resourceUrl}". To customise, use:\n\ndefineResource('${resourceUrl}', {\n` + operationsLine + `  fields: [\n${fieldLines}\n  ]\n})`);
}
//#endregion
//#region assets/crud/crud/useSmartCrudOperation.ts
function areEqualValues(left, right) {
	if (Object.is(left, right)) return true;
	if (left instanceof Date && right instanceof Date) return left.getTime() === right.getTime();
	if (Array.isArray(left) && Array.isArray(right)) {
		if (left.length !== right.length) return false;
		return left.every((item, index) => areEqualValues(item, right[index]));
	}
	return false;
}
function areEqualFormData(left, right) {
	if (left === right) return true;
	if (left == null || right == null) return false;
	const leftKeys = Object.keys(left);
	const rightKeys = Object.keys(right);
	if (leftKeys.length !== rightKeys.length) return false;
	return leftKeys.every((key) => Object.prototype.hasOwnProperty.call(right, key) && areEqualValues(left[key], right[key]));
}
function stateFromRouting(routingState) {
	if (routingState.initialRecordId != null) return {
		activeOperation: "edit",
		formData: null
	};
	if (routingState.initialIsNew) return {
		activeOperation: "create",
		formData: {}
	};
	return {
		activeOperation: null,
		formData: null
	};
}
function crudReducer(state, action) {
	switch (action.type) {
		case "sync-routing": {
			const nextState = stateFromRouting(action.routingState);
			return state.activeOperation === nextState.activeOperation && areEqualFormData(state.formData, nextState.formData) ? state : nextState;
		}
		case "set-form-data": return areEqualFormData(state.formData, action.data) ? state : {
			...state,
			formData: action.data
		};
		case "create": return state.activeOperation === "create" && areEqualFormData(state.formData, {}) ? state : {
			activeOperation: "create",
			formData: {}
		};
		case "edit": return state.activeOperation === "edit" && state.formData === null ? state : {
			activeOperation: "edit",
			formData: null
		};
		case "reset": return state.activeOperation === null && state.formData === null ? state : {
			activeOperation: null,
			formData: null
		};
		default: return state;
	}
}
/**
* Owns the `activeOperation` + `formData` state pair and keeps them in sync
* with routing changes and the resource event bus (ADD / EDIT / CANCEL / SUCCESS).
*
* Extracted from SmartCrudPage to consolidate three separate useEffects that
* previously managed these coupled states independently.
*/
function useSmartCrudOperation(events, routingState) {
	const [on] = useEvents();
	const [{ activeOperation, formData }, dispatchState] = useReducer(crudReducer, routingState, stateFromRouting);
	const handleFormDataChange = useCallback((data) => {
		dispatchState({
			type: "set-form-data",
			data
		});
	}, []);
	const startCreate = useCallback(() => {
		dispatchState({ type: "create" });
	}, []);
	const startEdit = useCallback(() => {
		dispatchState({ type: "edit" });
	}, []);
	const resetOperation = useCallback(() => {
		dispatchState({ type: "reset" });
	}, []);
	useEffect(() => {
		dispatchState({
			type: "sync-routing",
			routingState: {
				initialRecordId: routingState.initialRecordId,
				initialIsNew: routingState.initialIsNew
			}
		});
	}, [routingState.initialIsNew, routingState.initialRecordId]);
	useEffect(() => {
		const subscriptions = [];
		if (events?.ADD) subscriptions.push(on(events.ADD, () => {
			dispatchState({ type: "create" });
		}));
		if (events?.EDIT) subscriptions.push(on(events.EDIT, () => {
			dispatchState({ type: "edit" });
		}));
		if (events?.CANCEL) subscriptions.push(on(events.CANCEL, () => {
			dispatchState({ type: "reset" });
		}));
		if (events?.SUCCESS) subscriptions.push(on(events.SUCCESS, () => {
			dispatchState({ type: "reset" });
		}));
		return () => {
			subscriptions.forEach((sub) => sub.unsubscribe());
		};
	}, [
		events?.ADD,
		events?.CANCEL,
		events?.EDIT,
		events?.SUCCESS,
		on
	]);
	return {
		activeOperation,
		formData,
		handleFormDataChange,
		startCreate,
		startEdit,
		resetOperation
	};
}
//#endregion
//#region assets/crud/crud/rules/useFieldPermissions.ts
/**
* Filters and marks fields based on user roles.
* IMPORTANT: This is UI-only. Backend is the real authorization source.
*
* - If field.permissions.visible is set and userRoles has no intersection → filter out the field
* - If field.permissions.editable is set and userRoles has no intersection → mark field as disabled
*
* Identity fields (isIdentity === true) are always kept regardless of permissions
* so grids and forms can resolve row keys consistently.
*/
function useFieldPermissions(fields, userRoles) {
	return useMemo(() => {
		return fields.reduce((acc, field) => {
			if (field.isIdentity) {
				acc.push(field);
				return acc;
			}
			const perms = field.permissions;
			if (!perms) {
				acc.push(field);
				return acc;
			}
			if (perms.visible && !perms.visible.some((r) => userRoles.includes(r))) return acc;
			if (perms.editable && !perms.editable.some((r) => userRoles.includes(r))) {
				acc.push({
					...field,
					disabledWhen: () => true
				});
				return acc;
			}
			acc.push(field);
			return acc;
		}, []);
	}, [fields, userRoles]);
}
//#endregion
//#region assets/crud/crud/rules/useConditionalRules.ts
function isEmptyRuleValue(value) {
	return value === null || value === void 0 || value === "";
}
function evaluateConditionalRuleState(field, formData) {
	const baseVisible = field.visibleOnForm;
	const baseRequired = field.required;
	if (formData == null) return {
		name: field.name,
		visible: baseVisible,
		disabled: false,
		required: baseRequired,
		computedValue: void 0
	};
	const visible = field.visibleWhen ? baseVisible && field.visibleWhen(formData) : baseVisible;
	const disabled = field.disabledWhen ? field.disabledWhen(formData) : false;
	const required = field.requiredWhen ? baseRequired || field.requiredWhen(formData) : baseRequired;
	if (!visible && field.clearWhenHidden) return {
		name: field.name,
		visible,
		disabled,
		required,
		computedValue: null
	};
	let computedValue = field.computed ? field.computed(formData) : void 0;
	if (computedValue === void 0 && field.defaultWhen && isEmptyRuleValue(formData[field.name])) computedValue = field.defaultWhen(formData);
	return {
		name: field.name,
		visible,
		disabled,
		required,
		computedValue
	};
}
/**
* Given the current Field definitions and live form data,
* returns the reactive state (visible/disabled/required/computedValue) for each field.
*
* Pure computation — re-runs whenever formData changes.
*
* When `formData` is `null`, SmartCrud has not hydrated the form snapshot yet
* (typically while opening an edit dialog), so rules are skipped and defaults
* are returned. Empty objects `{}` are treated as valid create-form state.
*/
function useConditionalRules(fields, formData) {
	return useMemo(() => {
		return fields.map((field) => evaluateConditionalRuleState(field, formData));
	}, [fields, formData]);
}
//#endregion
//#region assets/crud/crud/rules/useDependsOn.ts
/**
* Compares two primitive-or-reference values.
* Uses strict equality for primitives (covers the vast majority of form values).
* Falls back to JSON.stringify only for objects/arrays (rare: e.g. multi-select).
*/
function valuesEqual(a, b) {
	if (a === b) return true;
	if (a == null || b == null) return false;
	if (typeof a !== "object" || typeof b !== "object") return false;
	return JSON.stringify(a) === JSON.stringify(b);
}
/**
* For each field that declares `dependsOn`, invalidate its entity query
* (keyed by the field's `url`) whenever one of the watched form values changes.
*
* Only fires when a dependency value actually changes (compared per-value).
* Skips the initial mount run to prevent spurious refetches when a dialog opens
* with already-cached data (BUG-009).
*
* Also skips evaluation while `formData` is `null`, which SmartCrudPage uses
* for edit dialogs that have not hydrated their initial snapshot yet.
*/
function useDependsOn(fields, formData) {
	const queryClient = useQueryClient();
	const dependentFields = fields.filter((field) => field.dependsOn?.length);
	const currentEntries = formData == null ? null : dependentFields.map((field) => ({
		url: field.url ?? null,
		values: (field.dependsOn ?? []).map((dep) => formData[dep])
	}));
	const isMounted = useRef(false);
	const previousEntriesRef = useRef(null);
	useEffect(() => {
		if (!isMounted.current) {
			isMounted.current = true;
			previousEntriesRef.current = currentEntries;
			return;
		}
		if (currentEntries == null) {
			previousEntriesRef.current = null;
			return;
		}
		const previousEntries = previousEntriesRef.current ?? [];
		previousEntriesRef.current = currentEntries;
		currentEntries.forEach((entry, index) => {
			const previousValues = previousEntries[index]?.values ?? [];
			if (!(entry.values.length !== previousValues.length || entry.values.some((val, i) => !valuesEqual(val, previousValues[i]))) || !entry.url) return;
			queryClient.invalidateQueries({ queryKey: [entry.url] });
		});
	}, [JSON.stringify(currentEntries?.map((e) => e.values) ?? null), queryClient]);
}
//#endregion
//#region assets/crud/crud/fieldOperationSemantics.ts
var SmartCrudFieldOperationSemanticsError = class extends Error {
	issues;
	constructor(message, issues = []) {
		super(message);
		this.name = "SmartCrudFieldOperationSemanticsError";
		this.issues = issues;
	}
};
const OPERATIONS = ["create", "edit"];
const PROPERTIES = [
	"visible",
	"required",
	"readonly",
	"disabled"
];
function normalizeOnly(only) {
	if (!only) return;
	return Array.isArray(only) ? only : [only];
}
function createEmptySemantics() {
	return {
		create: {},
		edit: {}
	};
}
function applyProperty(target, issues, owner, operation, property, value, source) {
	const current = target[operation][property];
	if (current !== void 0 && current !== value) {
		issues.push(`${owner} defines conflicting ${operation}.${property} semantics (${String(current)} vs ${String(value)} from ${source}).`);
		return;
	}
	target[operation][property] = value;
}
function applyFlagInput(target, issues, owner, property, input) {
	if (input === void 0) return;
	if (typeof input === "boolean") {
		OPERATIONS.forEach((operation) => {
			applyProperty(target, issues, owner, operation, property, input, property);
		});
		return;
	}
	OPERATIONS.forEach((operation) => {
		const value = input[operation];
		if (value === void 0) return;
		applyProperty(target, issues, owner, operation, property, value, `${property}.${operation}`);
	});
}
function applyStateInput(target, issues, owner, operation, state) {
	if (!state) return;
	PROPERTIES.forEach((property) => {
		const value = state[property];
		if (value === void 0) return;
		applyProperty(target, issues, owner, operation, property, value, `${operation}.${property}`);
	});
}
function validateResolvedSemantics(owner, semantics) {
	const issues = [];
	OPERATIONS.forEach((operation) => {
		const state = semantics[operation];
		if (state.visible === false && state.required === true) issues.push(`${owner} cannot be required when ${operation}.visible is false.`);
	});
	return issues;
}
function normalizeFieldOperationSemantics(behavior, owner = "Field operation semantics") {
	const semantics = createEmptySemantics();
	if (!behavior) return semantics;
	const issues = [];
	const allowedOperations = normalizeOnly(behavior.only);
	if (allowedOperations) {
		const allowedOperationSet = new Set(allowedOperations);
		OPERATIONS.forEach((operation) => {
			applyProperty(semantics, issues, owner, operation, "visible", allowedOperationSet.has(operation), "only");
		});
	}
	applyFlagInput(semantics, issues, owner, "visible", behavior.visible);
	applyFlagInput(semantics, issues, owner, "required", behavior.required);
	applyFlagInput(semantics, issues, owner, "readonly", behavior.readonly);
	applyFlagInput(semantics, issues, owner, "disabled", behavior.disabled);
	applyStateInput(semantics, issues, owner, "create", behavior.create);
	applyStateInput(semantics, issues, owner, "edit", behavior.edit);
	issues.push(...validateResolvedSemantics(owner, semantics));
	if (issues.length > 0) throw new SmartCrudFieldOperationSemanticsError("Invalid SmartCrud field operation semantics.", issues);
	return semantics;
}
/**
* Validates field operation semantics and returns any issues found,
* without throwing. Uses the same validation logic as
* `normalizeFieldOperationSemantics` but collects issues instead of
* throwing `SmartCrudFieldOperationSemanticsError`.
*/
function collectOperationSemanticsIssues(owner, behavior) {
	if (!behavior) return [];
	const semantics = createEmptySemantics();
	const issues = [];
	const allowedOperations = normalizeOnly(behavior.only);
	if (allowedOperations) {
		const allowedOperationSet = new Set(allowedOperations);
		OPERATIONS.forEach((operation) => {
			applyProperty(semantics, issues, owner, operation, "visible", allowedOperationSet.has(operation), "only");
		});
	}
	applyFlagInput(semantics, issues, owner, "visible", behavior.visible);
	applyFlagInput(semantics, issues, owner, "required", behavior.required);
	applyFlagInput(semantics, issues, owner, "readonly", behavior.readonly);
	applyFlagInput(semantics, issues, owner, "disabled", behavior.disabled);
	applyStateInput(semantics, issues, owner, "create", behavior.create);
	applyStateInput(semantics, issues, owner, "edit", behavior.edit);
	issues.push(...validateResolvedSemantics(owner, semantics));
	return issues;
}
function resolveBaseVisibility(field) {
	if (field.visible !== void 0) return field.visible;
	if (field.hidden !== void 0) return !field.hidden;
	return true;
}
function resolveBaseState(field) {
	return {
		visible: resolveBaseVisibility(field),
		required: field.required ?? false,
		readonly: field.readonly ?? false,
		disabled: field.disabled ?? false
	};
}
function resolveFieldOperationSemantics(field, behavior, owner = "Field operation semantics") {
	const normalized = normalizeFieldOperationSemantics(behavior, owner);
	return OPERATIONS.reduce((resolved, operation) => {
		const baseState = resolveBaseState(field);
		resolved[operation] = {
			visible: normalized[operation].visible ?? baseState.visible,
			required: normalized[operation].required ?? baseState.required,
			readonly: normalized[operation].readonly ?? baseState.readonly,
			disabled: normalized[operation].disabled ?? baseState.disabled
		};
		return resolved;
	}, {});
}
function applyFieldOperationSemantics(field, operation, behavior, owner = `Field '${field.name}'`, target = "grid") {
	const resolved = resolveFieldOperationSemantics(field, behavior, owner)[operation];
	return {
		...field,
		...target === "form" ? { visibleOnForm: resolved.visible } : { visible: resolved.visible },
		hidden: !resolved.visible,
		required: resolved.required,
		readonly: resolved.readonly,
		disabled: resolved.disabled
	};
}
//#endregion
//#region assets/crud/crud/useSmartCrudFields.ts
/**
* Runs the full SmartCrud field processing pipeline:
*   1. Operation semantics — adjusts visibility/required/readonly per create/edit
*   2. RBAC — filters or disables fields based on user roles
*   3. Conditional rules — evaluates visibleWhen / disabledWhen / requiredWhen / defaultWhen / computed
*   4. Dependency invalidation — refetches dependent field options on change
*   5. Field state merge — applies conditional state back to field definitions
*
* Extracted from SmartCrudPage to isolate the declarative rules pipeline
* from schema loading and routing concerns.
*/
function useSmartCrudFields(fields, activeOperation, formData, roles) {
	const permissionFilteredFields = useFieldPermissions(useMemo(() => {
		if (!activeOperation) return fields;
		return fields.map((field) => {
			const runtimeField = field;
			if (!runtimeField.operation) return field;
			return applyFieldOperationSemantics(runtimeField, activeOperation, runtimeField.operation, `Field '${field.name}'`, "form");
		});
	}, [activeOperation, fields]), roles);
	const fieldStates = useConditionalRules(permissionFilteredFields, formData);
	useDependsOn(permissionFilteredFields, formData);
	return {
		processedFields: useMemo(() => {
			const stateByName = new Map(fieldStates.map((s) => [s.name, s]));
			return permissionFilteredFields.map((field) => {
				if (field.isIdentity) return field;
				const state = stateByName.get(field.name);
				if (!state) return field;
				const updated = { ...field };
				updated.visibleOnForm = state.visible;
				if (activeOperation) updated.hidden = !state.visible;
				if (state.disabled) updated.disabled = true;
				updated.required = state.required;
				if (state.computedValue !== void 0) updated.readonly = true;
				return updated;
			});
		}, [permissionFilteredFields, fieldStates]),
		computedValues: useMemo(() => fieldStates.reduce((acc, state) => {
			if (state.computedValue !== void 0) acc[state.name] = state.computedValue;
			return acc;
		}, {}), [fieldStates])
	};
}
//#endregion
//#region assets/crud/crud/fieldValidation.ts
var SmartCrudFieldContractError = class extends Error {
	issues;
	constructor(message, issues = []) {
		super(message);
		this.name = "SmartCrudFieldContractError";
		this.issues = issues;
	}
};
const CONTRACT_KEYS = ["source", "strategy"];
const HYDRA_CONTRACT_KEYS = [...CONTRACT_KEYS, "directives"];
const MANUAL_CONTRACT_KEYS = [...CONTRACT_KEYS, "fields"];
const OVERRIDE_DIRECTIVE_KEYS = [
	"kind",
	"key",
	"patch"
];
const REMOVE_DIRECTIVE_KEYS = ["kind", "key"];
const SYNTHETIC_DIRECTIVE_KEYS = ["kind", "field"];
const OPERATION_BEHAVIOR_KEYS = [
	"only",
	"visible",
	"required",
	"readonly",
	"disabled",
	"create",
	"edit"
];
const OPERATION_STATE_KEYS = [
	"visible",
	"required",
	"readonly",
	"disabled"
];
const MANUAL_FIELD_KEYS = [...[
	"isIdentity",
	"type",
	"col",
	"name",
	"label",
	"width",
	"height",
	"minWidth",
	"align",
	"sortable",
	"filterable",
	"hideable",
	"validators",
	"url",
	"loadOptions",
	"filters",
	"byKeyUrl",
	"textField",
	"valueField",
	"valueType",
	"format",
	"selectedFilterOperation",
	"filterValue",
	"data",
	"formatter",
	"itemFormatter",
	"visible",
	"defaultValue",
	"autoSelectIfSingle",
	"onChange",
	"onSelect",
	"onClick",
	"readonly",
	"disabled",
	"hidden",
	"required",
	"precision",
	"accept",
	"buttons",
	"searchEnabled",
	"searchExpr",
	"helpText",
	"contentRender",
	"visibleOnForm",
	"maxLength",
	"multiple",
	"sendAsString",
	"order",
	"visibleWhen",
	"disabledWhen",
	"computed",
	"defaultWhen",
	"requiredWhen",
	"clearWhenHidden",
	"dependsOn",
	"permissions"
], "operation"];
const FIELD_PATCH_KEYS = MANUAL_FIELD_KEYS.filter((key) => key !== "name");
function normalizeKey(value) {
	return value.trim();
}
function isPlainObject(value) {
	return typeof value === "object" && value !== null && !Array.isArray(value);
}
function formatAllowedKeys(keys) {
	return keys.join(", ");
}
function collectUnknownKeyIssues(owner, value, allowedKeys) {
	if (!isPlainObject(value)) return [`${owner} must be an object.`];
	return Object.keys(value).filter((key) => !allowedKeys.includes(key)).sort((left, right) => left.localeCompare(right)).map((key) => `${owner} contains unknown key '${key}'. Allowed keys: ${formatAllowedKeys(allowedKeys)}.`);
}
function collectOperationBehaviorIssues(owner, behavior) {
	if (behavior === void 0) return [];
	const issues = collectUnknownKeyIssues(`${owner} operation`, behavior, OPERATION_BEHAVIOR_KEYS);
	if (isPlainObject(behavior)) {
		if (behavior.create !== void 0) issues.push(...collectUnknownKeyIssues(`${owner} operation.create`, behavior.create, OPERATION_STATE_KEYS));
		if (behavior.edit !== void 0) issues.push(...collectUnknownKeyIssues(`${owner} operation.edit`, behavior.edit, OPERATION_STATE_KEYS));
	}
	issues.push(...collectOperationSemanticsIssues(owner, behavior));
	return issues;
}
function collectHydraFieldDirectiveIssues(directive, index) {
	const owner = `Directive #${index + 1}`;
	if (directive.kind === "override") return [
		...collectUnknownKeyIssues(owner, directive, OVERRIDE_DIRECTIVE_KEYS),
		...collectUnknownKeyIssues(`Override '${normalizeKey(String(directive.key ?? ""))}' patch`, directive.patch, FIELD_PATCH_KEYS),
		...collectOperationBehaviorIssues(`Override '${normalizeKey(String(directive.key ?? ""))}'`, directive.patch.operation)
	];
	if (directive.kind === "remove") return collectUnknownKeyIssues(owner, directive, REMOVE_DIRECTIVE_KEYS);
	const fieldName = normalizeKey(directive.field?.name ?? "");
	return [
		...collectUnknownKeyIssues(owner, directive, SYNTHETIC_DIRECTIVE_KEYS),
		...collectUnknownKeyIssues(`Synthetic field '${fieldName || `#${index + 1}`}'`, directive.field, MANUAL_FIELD_KEYS),
		...collectOperationBehaviorIssues(`Synthetic field '${fieldName || `#${index + 1}`}'`, directive.field.operation)
	];
}
function collectManualFieldIssues(field, index) {
	const fieldName = normalizeKey(field.name ?? "");
	return [...collectUnknownKeyIssues(`Manual field #${index + 1}`, field, MANUAL_FIELD_KEYS), ...collectOperationBehaviorIssues(`Manual field '${fieldName || `#${index + 1}`}'`, field.operation)];
}
function validateFieldContract(contract) {
	const issues = [];
	if (contract.source === "hydra") {
		issues.push(...collectUnknownKeyIssues("Hydra contract", contract, HYDRA_CONTRACT_KEYS));
		if (contract.strategy !== "augment") issues.push("Hydra contracts must use strategy 'augment'.");
		const targetedKeys = /* @__PURE__ */ new Map();
		const syntheticNames = /* @__PURE__ */ new Set();
		contract.directives.forEach((directive, index) => {
			issues.push(...collectHydraFieldDirectiveIssues(directive, index));
			if (directive.kind === "override" || directive.kind === "remove") {
				const key = normalizeKey(directive.key);
				if (!key) {
					issues.push(`Directive #${index + 1} must reference a non-empty field key.`);
					return;
				}
				const previous = targetedKeys.get(key);
				if (previous) issues.push(`Field '${key}' is targeted more than once in hydra directives (${previous} + ${directive.kind}).`);
				else targetedKeys.set(key, directive.kind);
				return;
			}
			const fieldName = normalizeKey(directive.field.name);
			if (!fieldName) {
				issues.push(`Directive #${index + 1} must define a non-empty synthetic field name.`);
				return;
			}
			if (syntheticNames.has(fieldName)) issues.push(`Synthetic field '${fieldName}' is declared more than once.`);
			else syntheticNames.add(fieldName);
		});
	}
	if (contract.source === "manual") {
		issues.push(...collectUnknownKeyIssues("Manual contract", contract, MANUAL_CONTRACT_KEYS));
		if (contract.strategy !== "replace") issues.push("Manual contracts must use strategy 'replace'.");
		const names = /* @__PURE__ */ new Set();
		contract.fields.forEach((field, index) => {
			issues.push(...collectManualFieldIssues(field, index));
			const fieldName = normalizeKey(field.name);
			if (!fieldName) {
				issues.push(`Manual field #${index + 1} must define a non-empty name.`);
				return;
			}
			if (names.has(fieldName)) issues.push(`Manual field '${fieldName}' is declared more than once.`);
			else names.add(fieldName);
		});
	}
	if (issues.length > 0) throw new SmartCrudFieldContractError("Invalid SmartCrud field contract.", issues);
	return contract;
}
function validateUniqueFieldNames(fields, owner) {
	const seen = /* @__PURE__ */ new Set();
	const duplicates = [];
	fields.forEach((field, index) => {
		const name = field.name.trim();
		if (!name) {
			duplicates.push(`${owner} field #${index + 1} must define a non-empty name.`);
			return;
		}
		if (seen.has(name)) {
			duplicates.push(`${owner} field '${name}' is declared more than once.`);
			return;
		}
		seen.add(name);
	});
	if (duplicates.length > 0) throw new SmartCrudFieldContractError("Invalid SmartCrud field resolution input.", duplicates);
}
function validateHydraFieldResolutionInput(baselineFields, contract) {
	validateUniqueFieldNames(baselineFields, "Hydra baseline");
	const baselineByName = new Map(baselineFields.map((field) => [field.name, field]));
	const issues = [];
	contract.directives.forEach((directive) => {
		if (directive.kind === "override" || directive.kind === "remove") {
			if (!baselineByName.has(directive.key)) issues.push(`${directive.kind === "override" ? "Override" : "Remove"} targets unknown Hydra field '${directive.key}'.`);
			return;
		}
		const syntheticName = normalizeKey(directive.field.name);
		if (baselineByName.has(syntheticName)) issues.push(`Synthetic field '${syntheticName}' collides with an inferred Hydra field. Use 'override' instead.`);
	});
	if (issues.length > 0) throw new SmartCrudFieldContractError("Invalid SmartCrud hydra field resolution.", issues);
}
function validateResolvedFieldNames(fields, owner) {
	validateUniqueFieldNames(fields, owner);
}
//#endregion
//#region assets/crud/crud/resolveSmartCrudFields.ts
function cloneField(field) {
	return { ...field };
}
function applyFieldPatch(field, patch) {
	return {
		...field,
		...patch
	};
}
function toResolvedManualField(field) {
	return cloneField(field);
}
function resolveHydraFields(baselineFields, contract) {
	validateHydraFieldResolutionInput(baselineFields, contract);
	const overrides = /* @__PURE__ */ new Map();
	const removals = /* @__PURE__ */ new Set();
	const prepend = [];
	const append = [];
	contract.directives.forEach((directive) => {
		if (directive.kind === "override") {
			overrides.set(directive.key, directive.patch);
			return;
		}
		if (directive.kind === "remove") {
			removals.add(directive.key);
			return;
		}
		const syntheticField = toResolvedManualField(directive.field);
		if (directive.kind === "prepend") {
			prepend.push(syntheticField);
			return;
		}
		append.push(syntheticField);
	});
	const resolvedBaseline = baselineFields.flatMap((field) => {
		if (removals.has(field.name)) return [];
		const patch = overrides.get(field.name);
		return [patch ? applyFieldPatch(field, patch) : cloneField(field)];
	});
	const resolved = [
		...prepend,
		...resolvedBaseline,
		...append
	];
	validateResolvedFieldNames(resolved, "Resolved SmartCrud");
	return resolved;
}
function mergeOverrides(inferred, overrides) {
	return inferred.map((field) => {
		const override = overrides.find((entry) => entry.key === field.name);
		if (!override) return cloneField(field);
		const { key, ...overrideProps } = override;
		return {
			...field,
			...overrideProps
		};
	});
}
function resolveSmartCrudFields(options) {
	const { baselineFields = [], contract, legacyOverrides = [] } = options;
	if (!contract) return legacyOverrides.length > 0 ? mergeOverrides([...baselineFields], legacyOverrides) : [...baselineFields].map(cloneField);
	validateFieldContract(contract);
	if (contract.source === "manual") return contract.fields.map((field) => toResolvedManualField(field));
	return resolveHydraFields(baselineFields, contract);
}
//#endregion
//#region assets/crud/schema/ResourceSchema.tsx
const ResourceSchemaResolverContext = createContext(null);
function ResourceSchemaProvider({ children, resolver }) {
	return /* @__PURE__ */ jsx(ResourceSchemaResolverContext.Provider, {
		value: resolver,
		children
	});
}
function resolveWithRuntimeErrors(resolver, supportedOperations = []) {
	try {
		return {
			fields: resolver(),
			isLoading: false,
			error: void 0,
			supportedOperations
		};
	} catch (runtimeError) {
		return {
			fields: [],
			isLoading: false,
			error: runtimeError instanceof Error ? runtimeError : new Error(String(runtimeError)),
			supportedOperations
		};
	}
}
function useResolvedResourceFields({ apiUrl, manualFields, overrides, fieldContract }) {
	const schemaResolver = useContext(ResourceSchemaResolverContext);
	if (fieldContract?.source === "manual") return resolveWithRuntimeErrors(() => resolveSmartCrudFields({ contract: fieldContract }));
	if (manualFields && manualFields.length > 0) return {
		fields: manualFields,
		isLoading: false,
		error: void 0,
		supportedOperations: []
	};
	if (!schemaResolver) return {
		fields: [],
		isLoading: false,
		error: /* @__PURE__ */ new Error(`No ResourceSchema resolver configured for ${apiUrl}. Wrap your app with ResourceSchemaProvider or a backend-specific provider such as HydraResourceSchemaProvider.`),
		supportedOperations: []
	};
	const baseline = schemaResolver.useResourceSchema({ apiUrl });
	if (baseline.isLoading || baseline.error) return baseline;
	return resolveWithRuntimeErrors(() => resolveSmartCrudFields({
		baselineFields: baseline.fields,
		contract: fieldContract,
		legacyOverrides: fieldContract ? void 0 : overrides
	}), baseline.supportedOperations);
}
//#endregion
//#region assets/crud/crud/SmartCrudPage.tsx
function CrudSkeleton() {
	return /* @__PURE__ */ jsxs("div", {
		className: "p-6 space-y-4 animate-pulse",
		children: [/* @__PURE__ */ jsx("div", { className: "h-8 bg-gray-200 rounded w-1/3" }), /* @__PURE__ */ jsx("div", { className: "h-64 bg-gray-200 rounded" })]
	});
}
function CrudError({ message, onRetry }) {
	const { t } = useCoreTranslation();
	return /* @__PURE__ */ jsxs("div", {
		className: "p-6 space-y-3 text-sm text-secondary",
		role: "alert",
		children: [
			/* @__PURE__ */ jsx("p", {
				className: "m-0 text-base font-semibold text-primary",
				children: t("crudPage.schemaErrorTitle")
			}),
			/* @__PURE__ */ jsx("p", {
				className: "m-0 max-w-2xl",
				children: t("crudPage.schemaErrorMessage")
			}),
			/* @__PURE__ */ jsxs("details", {
				className: "max-w-2xl text-xs",
				children: [/* @__PURE__ */ jsx("summary", {
					className: "cursor-pointer text-secondary",
					children: t("crudPage.schemaErrorDetails")
				}), /* @__PURE__ */ jsx("pre", {
					className: "mt-2 overflow-auto rounded border border-default p-2 whitespace-pre-wrap",
					children: message
				})]
			}),
			/* @__PURE__ */ jsx(Button, {
				onClick: onRetry,
				variant: "secondary",
				size: "sm",
				children: t("crudPage.schemaErrorRetry")
			})
		]
	});
}
function formatRuntimeErrorMessage(error) {
	const issues = "issues" in error && Array.isArray(error.issues) ? error.issues : [];
	return issues.length > 0 ? `${error.message} ${issues.join(" ")}` : error.message;
}
/**
* SmartCrudPage — wraps CrudPage with Hydra auto-discovery and declarative rules.
*
* ## Field resolution
* - If `resource.fields` is a non-empty array, it is used as-is (pass-through).
* - If `resource.fields` is empty / absent, fields are inferred from the Hydra
*   API spec at `/api/docs.json` via `useResourceSchema`.
* - While the schema loads: renders a skeleton loader.
* - If the schema fetch errors: renders a user-visible error with a retry button.
*
* ## Declarative rules pipeline (via `useSmartCrudFields`)
*   1. `useFieldPermissions` — RBAC: hides or disables fields based on user roles
*   2. `useConditionalRules` — evaluates visibleWhen / disabledWhen / computed
*   3. `useDependsOn`        — invalidates entity queries when dependency fields change
*
* ## Operation & form state (via `useSmartCrudOperation`)
*   Manages `activeOperation` + `formData` as a unified state pair, synced
*   from routing changes and the resource event bus (ADD/EDIT/CANCEL/SUCCESS).
*
* URL deep-linking is wired via `initialRecordId` / `initialIsNew` props on CrudPage.
*/
function SmartCrudPage({ resource, fieldOverrides, formRef, onSelectionChanged, editDisabled, deleteDisabled, gridRef }) {
	const queryClient = useQueryClient();
	const internalGridRef = useRef(null);
	const effectiveGridRef = gridRef ?? internalGridRef;
	const resolvedBaseResource = useMemo(() => resolveCrudResource(resource), [resource]);
	const hasManualFields = !resource.fieldContract && Array.isArray(resource.fields) && resource.fields.length > 0;
	const { fields, isLoading, error, supportedOperations } = useResolvedResourceFields({
		apiUrl: resolvedBaseResource.apiUrl,
		manualFields: hasManualFields ? resource.fields : void 0,
		overrides: hasManualFields ? void 0 : fieldOverrides,
		fieldContract: resource.fieldContract
	});
	const hintFiredRef = useRef(false);
	useEffect(() => {
		if (!resource.fieldContract && !hasManualFields && !isLoading && !error && fields.length > 0 && !hintFiredRef.current) {
			hintFiredRef.current = true;
			logDevHint(resource.apiUrl, fields, supportedOperations);
		}
	}, [
		resource.fieldContract,
		hasManualFields,
		isLoading,
		error,
		fields,
		resource.apiUrl,
		supportedOperations
	]);
	const routingState = useRouting(resource.routing);
	const { activeOperation, formData, handleFormDataChange, startCreate, startEdit, resetOperation } = useSmartCrudOperation(void 0, routingState);
	const roles = useSmartCrudRoles();
	const { processedFields, computedValues } = useSmartCrudFields(fields, activeOperation, formData, useMemo(() => roles ?? [], [roles]));
	useMercureSubscription(resource.apiUrl, () => {
		effectiveGridRef.current?.refresh();
	}, resolvedBaseResource.mercure !== false);
	const normalizedApiUrl = resolvedBaseResource.apiUrl.startsWith("/") ? resolvedBaseResource.apiUrl : `/${resolvedBaseResource.apiUrl}`;
	const resolvedResource = useMemo(() => ({
		...resolvedBaseResource,
		...!hasManualFields ? { fields } : {},
		apiUrl: normalizedApiUrl,
		fields: processedFields,
		_supportedOperations: supportedOperations
	}), [
		fields,
		hasManualFields,
		normalizedApiUrl,
		processedFields,
		resolvedBaseResource,
		supportedOperations
	]);
	if (!hasManualFields && isLoading) return /* @__PURE__ */ jsx(CrudSkeleton, {});
	if (!hasManualFields && error) return /* @__PURE__ */ jsx(CrudError, {
		message: formatRuntimeErrorMessage(error),
		onRetry: () => queryClient.invalidateQueries()
	});
	return /* @__PURE__ */ jsx(CrudPage, {
		resource: resolvedResource,
		onFormDataChange: handleFormDataChange,
		computedValues,
		initialRecordId: routingState.initialRecordId,
		initialIsNew: routingState.initialIsNew,
		initialFilters: routingState.initialFilters,
		onFiltersChange: routingState.syncFilters,
		formRef,
		onSelectionChanged,
		editDisabled,
		deleteDisabled,
		gridRef: effectiveGridRef,
		onOperationChange: (operation) => {
			if (operation === "create") {
				startCreate();
				return;
			}
			if (operation === "edit") {
				startEdit();
				return;
			}
			resetOperation();
		}
	});
}
//#endregion
//#region assets/crud/crud/fieldContract.ts
function defineFields(contract) {
	return validateFieldContract(contract);
}
const defineFieldContract = defineFields;
//#endregion
//#region assets/crud/field/BaseFieldBuilder.ts
var BaseFieldBuilder = class {
	_field;
	constructor(type) {
		this._field = {
			isIdentity: false,
			type,
			col: 12,
			name: "",
			label: "",
			width: void 0,
			height: null,
			minWidth: void 0,
			align: "left",
			sortable: true,
			filterable: true,
			hideable: true,
			validators: [],
			url: void 0,
			loadOptions: [],
			filters: [],
			byKeyUrl: null,
			textField: "",
			valueField: "",
			valueType: "string",
			filterValue: void 0,
			selectedFilterOperation: void 0,
			data: [],
			format: "",
			formatter: void 0,
			itemFormatter: void 0,
			visible: true,
			defaultValue: void 0,
			onChange: void 0,
			onSelect: void 0,
			onClick: void 0,
			readonly: false,
			disabled: false,
			hidden: false,
			required: false,
			precision: 0,
			accept: null,
			buttons: [],
			searchEnabled: true,
			searchExpr: null,
			helpText: void 0,
			contentRender: null,
			visibleOnForm: true,
			maxLength: void 0,
			multiple: false,
			autoSelectIfSingle: false,
			sendAsString: false
		};
	}
	isIdentity(isIdentity) {
		this._field.isIdentity = isIdentity;
		return this;
	}
	col(col) {
		this._field.col = col;
		return this;
	}
	name(name) {
		this._field.name = name;
		return this;
	}
	label(label) {
		this._field.label = label;
		return this;
	}
	width(width) {
		this._field.width = width;
		return this;
	}
	height(height) {
		this._field.height = height;
		return this;
	}
	minWidth(minWidth) {
		this._field.minWidth = minWidth;
		return this;
	}
	align(align) {
		this._field.align = align;
		return this;
	}
	sortable(sortable) {
		this._field.sortable = sortable;
		return this;
	}
	filterable(filterable) {
		this._field.filterable = filterable;
		return this;
	}
	hideable(hideable) {
		this._field.hideable = hideable;
		return this;
	}
	validators(validators) {
		this._field.validators = validators;
		return this;
	}
	valueType(valueType) {
		this._field.valueType = valueType;
		return this;
	}
	filterValue(filterValue) {
		this._field.filterValue = filterValue;
		return this;
	}
	data(data) {
		this._field.data = data;
		return this;
	}
	format(format) {
		this._field.format = format;
		return this;
	}
	formatter(formatter) {
		this._field.formatter = formatter;
		return this;
	}
	itemFormatter(itemFormatter) {
		this._field.itemFormatter = itemFormatter;
		return this;
	}
	visible(visible) {
		this._field.visible = visible;
		return this;
	}
	defaultValue(defaultValue) {
		this._field.defaultValue = defaultValue;
		return this;
	}
	onChange(onChange) {
		this._field.onChange = onChange;
		return this;
	}
	onSelect(onSelect) {
		this._field.onSelect = onSelect;
		return this;
	}
	onClick(onClick) {
		this._field.onClick = onClick;
		return this;
	}
	readonly(readonly) {
		this._field.readonly = readonly;
		return this;
	}
	disabled(disabled) {
		this._field.disabled = disabled;
		return this;
	}
	hidden(hidden) {
		this._field.hidden = hidden;
		return this;
	}
	required(required) {
		this._field.required = required;
		return this;
	}
	precision(value) {
		this._field.precision = value;
		return this;
	}
	accept(value) {
		this._field.accept = value;
		return this;
	}
	buttons(value) {
		this._field.buttons = value;
		return this;
	}
	searchEnabled(value) {
		this._field.searchEnabled = value;
		return this;
	}
	searchExpr(value) {
		this._field.searchExpr = value;
		return this;
	}
	helpText(value) {
		this._field.helpText = value;
		return this;
	}
	contentRender(value) {
		this._field.contentRender = value;
		return this;
	}
	visibleOnForm(value) {
		this._field.visibleOnForm = value;
		return this;
	}
	autoSelectIfSingle(value) {
		this._field.autoSelectIfSingle = value;
		return this;
	}
	url(url) {
		this._field.url = url;
		return this;
	}
	loadOptions(param) {
		this._field.loadOptions = param;
		return this;
	}
	filters(filters) {
		this._field.filters = filters;
		return this;
	}
	byKeyUrl(byKeyUrl) {
		this._field.byKeyUrl = byKeyUrl;
		return this;
	}
	textField(textField) {
		this._field.textField = textField;
		return this;
	}
	valueField(valueField) {
		this._field.valueField = valueField;
		return this;
	}
	selectedFilterOperation(operation) {
		this._field.selectedFilterOperation = operation;
		return this;
	}
	sendAsString(value) {
		this._field.sendAsString = value;
		return this;
	}
	multiple(value) {
		this._field.multiple = value;
		return this;
	}
	maxLength(value) {
		this._field.maxLength = value;
		return this;
	}
	visibleWhen(fn) {
		this._field.visibleWhen = fn;
		return this;
	}
	disabledWhen(fn) {
		this._field.disabledWhen = fn;
		return this;
	}
	computed(fn) {
		this._field.computed = fn;
		return this;
	}
	defaultWhen(fn) {
		this._field.defaultWhen = fn;
		return this;
	}
	requiredWhen(fn) {
		this._field.requiredWhen = fn;
		return this;
	}
	clearWhenHidden(value = true) {
		this._field.clearWhenHidden = value;
		return this;
	}
	dependsOn(fields) {
		this._field.dependsOn = fields;
		return this;
	}
	permissions(perms) {
		this._field.permissions = perms;
		return this;
	}
	build() {
		return { ...this._field };
	}
};
//#endregion
//#region assets/crud/field/FieldBuilders.ts
/**
* Factory for the standard hidden identity field (type NONE, name 'id').
*
* Usage:
*   identityField().build()
*   identityField().required(true).build()
*   identityField().valueType('number').build()
*
* Defaults set automatically:
*   isIdentity(true), visible(false), required(true), name('id'), label('Id')
*/
var IdentityFieldBuilder = class extends BaseFieldBuilder {
	constructor() {
		super("none");
		this._field.isIdentity = true;
		this._field.visible = false;
		this._field.required = true;
		this._field.name = "id";
		this._field.label = "Id";
	}
};
function identityField() {
	return new IdentityFieldBuilder();
}
/**
* Builder for ENTITY (remote select) fields.
*
* Required constructor args: url, valueField, textField — the three properties
* that are ALWAYS present on every ENTITY field in the codebase.
*
* Usage:
*   new EntityFieldBuilder('customers', 'id', 'businessName')
*     .label('Cliente')
*     .name('customer')
*     .formatter(cell => cell.value?.businessName)
*     .build()
*/
var EntityFieldBuilder = class extends BaseFieldBuilder {
	constructor(url, valueField, textField) {
		super("entity");
		this._field.url = url;
		this._field.valueField = valueField;
		this._field.textField = textField;
	}
};
function entityField(url, valueField, textField) {
	return new EntityFieldBuilder(url, valueField, textField);
}
/**
* Builder for CURRENCY fields.
*
* Defaults applied automatically:
*   sendAsString(true), align('right')
*
* Usage:
*   new CurrencyFieldBuilder()
*     .name('grandTotal')
*     .label('Total')
*     .formatter(cell => NumberUtils.formatCurrency(cell.value, cell.data?.currency))
*     .build()
*/
var CurrencyFieldBuilder = class extends BaseFieldBuilder {
	constructor() {
		super("currency");
		this._field.sendAsString = true;
		this._field.align = "right";
	}
};
function currencyField() {
	return new CurrencyFieldBuilder();
}
/**
* Factory for image upload fields (FILE type).
*
* Defaults: url=apiBaseUrl+'media', accept='image/*', align='center'
* Formatter must be provided by the caller since it depends on the data shape.
*
* Usage:
*   imageField('/api/')
*     .name('photo')
*     .label('Imagen')
*     .formatter(e => SharedUtils.imageFormatter(e.value?.path, e.data?.name))
*     .build()
*/
var ImageFieldBuilder = class extends BaseFieldBuilder {
	constructor(apiBaseUrl) {
		super("file");
		this._field.url = apiBaseUrl + "media";
		this._field.accept = "image/*";
		this._field.align = "center";
	}
};
function imageField(apiBaseUrl) {
	return new ImageFieldBuilder(apiBaseUrl);
}
/**
* Builder for DATE fields.
*
* Defaults applied automatically:
*   type = DATE, defaultValue = new Date()
*
* Usage:
*   dateField()
*     .name('issueDate')
*     .label('Fecha')
*     .formatter(cell => SharedUtils.dateFormatter(cell.value))
*     .build()
*/
var DateFieldBuilder = class extends BaseFieldBuilder {
	constructor() {
		super("date");
		this._field.defaultValue = new Intl.DateTimeFormat("en-CA", { timeZone: getCoreTimezone() }).format(/* @__PURE__ */ new Date());
	}
};
function dateField() {
	return new DateFieldBuilder();
}
/**
* Builder for DATETIME fields.
*
* Defaults applied automatically:
*   type = DATETIME, defaultValue = new Date()
*
* Usage:
*   datetimeField()
*     .name('openedAt')
*     .label('Apertura')
*     .formatter(cell => SharedUtils.dateTimeFormatter(cell.value))
*     .build()
*/
var DateTimeFieldBuilder = class extends BaseFieldBuilder {
	constructor() {
		super("datetime");
		this._field.defaultValue = /* @__PURE__ */ new Date();
	}
};
function datetimeField() {
	return new DateTimeFieldBuilder();
}
/**
* Builder for NUMBER fields.
*
* Usage:
*   numberField()
*     .name('quantity')
*     .label('Cantidad')
*     .precision(2)
*     .align('center')
*     .build()
*/
var NumberFieldBuilder = class extends BaseFieldBuilder {
	constructor() {
		super("number");
	}
};
function numberField() {
	return new NumberFieldBuilder();
}
/**
* Builder for ENUM (local select) fields.
*
* The options array is a required constructor arg — every ENUM field in the
* codebase always provides it.
*
* Usage:
*   enumField([
*     { value: 'income', text: 'Ingreso' },
*     { value: 'expense', text: 'Egreso' },
*   ])
*     .name('type')
*     .label('Tipo')
*     .formatter(cell => cell.value === 'income' ? 'Ingreso' : 'Egreso')
*     .build()
*/
var EnumFieldBuilder = class extends BaseFieldBuilder {
	constructor(options) {
		super("enum");
		this._field.data = options;
	}
};
function enumField(options) {
	return new EnumFieldBuilder(options);
}
/**
* Builder for TEXT (plain string input) fields.
*
* Usage:
*   textField()
*     .name('businessName')
*     .label('Razón social')
*     .required(true)
*     .build()
*/
var TextFieldBuilder = class extends BaseFieldBuilder {
	constructor() {
		super("text");
	}
};
function textField() {
	return new TextFieldBuilder();
}
/**
* Builder for TEXTAREA (multi-line text) fields.
*
* Usage:
*   textareaField()
*     .name('note')
*     .label('Nota')
*     .build()
*/
var TextareaFieldBuilder = class extends BaseFieldBuilder {
	constructor() {
		super("textarea");
	}
};
function textareaField() {
	return new TextareaFieldBuilder();
}
/**
* Builder for NONE (display-only, no editor) fields.
*
* Usage:
*   noneField()
*     .name('status')
*     .label('Status')
*     .formatter(cell => statusFormatter(cell.value))
*     .build()
*/
var NoneFieldBuilder = class extends BaseFieldBuilder {
	constructor() {
		super("none");
	}
};
function noneField() {
	return new NoneFieldBuilder();
}
/**
* Builder for SWITCH (boolean toggle) fields.
*
* Usage:
*   switchField()
*     .name('active')
*     .label('Activo')
*     .defaultValue(true)
*     .formatter(SharedUtils.booleanFormatter)
*     .build()
*/
var SwitchFieldBuilder = class extends BaseFieldBuilder {
	constructor() {
		super("switch");
	}
};
function switchField() {
	return new SwitchFieldBuilder();
}
/**
* Builder for SELECT (local dropdown, value+text pairs) fields.
*
* Usage:
*   selectField()
*     .name('currency')
*     .label('Moneda')
*     .data([{ value: 'PEN', text: 'Soles' }, { value: 'USD', text: 'Dólares' }])
*     .build()
*/
var SelectFieldBuilder = class extends BaseFieldBuilder {
	constructor() {
		super("select");
	}
};
function selectField() {
	return new SelectFieldBuilder();
}
/**
* Builder for CHECKBOX (boolean check) fields.
*
* Usage:
*   checkboxField()
*     .name('emitInvoice')
*     .label('Emitir comprobante')
*     .defaultValue(false)
*     .build()
*/
var CheckboxFieldBuilder = class extends BaseFieldBuilder {
	constructor() {
		super("checkbox");
	}
};
function checkboxField() {
	return new CheckboxFieldBuilder();
}
/**
* Builder for PASSWORD (masked text input) fields.
*
* Usage:
*   passwordField()
*     .name('password')
*     .label('Contraseña')
*     .required(true)
*     .build()
*/
var PasswordFieldBuilder = class extends BaseFieldBuilder {
	constructor() {
		super("password");
	}
};
function passwordField() {
	return new PasswordFieldBuilder();
}
//#endregion
//#region assets/crud/field/FieldBuilder.ts
var FieldBuilder = class {
	_field;
	constructor() {
		this._field = {
			isIdentity: false,
			type: "text",
			col: 12,
			name: "",
			label: "",
			width: void 0,
			height: null,
			minWidth: void 0,
			align: "left",
			sortable: true,
			filterable: true,
			hideable: true,
			validators: [],
			url: void 0,
			loadOptions: [],
			filters: [],
			byKeyUrl: null,
			textField: "",
			valueField: "",
			valueType: "string",
			selectedFilterOperation: void 0,
			filterValue: void 0,
			data: [],
			format: "",
			formatter: void 0,
			itemFormatter: void 0,
			visible: true,
			defaultValue: void 0,
			autoSelectIfSingle: false,
			onChange: void 0,
			onSelect: void 0,
			onClick: void 0,
			readonly: false,
			disabled: false,
			hidden: false,
			required: false,
			precision: 0,
			accept: null,
			buttons: [],
			searchEnabled: true,
			searchExpr: null,
			helpText: void 0,
			contentRender: null,
			visibleOnForm: true,
			maxLength: void 0,
			multiple: false,
			sendAsString: false
		};
	}
	isIdentity(isIdentity) {
		this._field.isIdentity = isIdentity;
		return this;
	}
	type(type) {
		this._field.type = type;
		return this;
	}
	col(col) {
		this._field.col = col;
		return this;
	}
	name(name) {
		this._field.name = name;
		return this;
	}
	label(label) {
		this._field.label = label;
		return this;
	}
	width(width) {
		this._field.width = width;
		return this;
	}
	height(height) {
		this._field.height = height;
		return this;
	}
	minWidth(minWidth) {
		this._field.minWidth = minWidth;
		return this;
	}
	align(align) {
		this._field.align = align;
		return this;
	}
	sortable(sortable) {
		this._field.sortable = sortable;
		return this;
	}
	filterable(filterable) {
		this._field.filterable = filterable;
		return this;
	}
	hideable(hideable) {
		this._field.hideable = hideable;
		return this;
	}
	validators(validators) {
		this._field.validators = validators;
		return this;
	}
	url(url) {
		this._field.url = url;
		return this;
	}
	loadOptions(param) {
		this._field.loadOptions = param;
		return this;
	}
	filters(filters) {
		this._field.filters = filters;
		return this;
	}
	byKeyUrl(byKeyUrl) {
		this._field.byKeyUrl = byKeyUrl;
		return this;
	}
	textField(textField) {
		this._field.textField = textField;
		return this;
	}
	valueField(valueField) {
		this._field.valueField = valueField;
		return this;
	}
	valueType(valueType) {
		this._field.valueType = valueType;
		return this;
	}
	selectedFilterOperation(operation) {
		this._field.selectedFilterOperation = operation;
		return this;
	}
	filterValue(filterValue) {
		this._field.filterValue = filterValue;
		return this;
	}
	data(data) {
		this._field.data = data;
		return this;
	}
	format(format) {
		this._field.format = format;
		return this;
	}
	formatter(formatter) {
		this._field.formatter = formatter;
		return this;
	}
	itemFormatter(itemFormatter) {
		this._field.itemFormatter = itemFormatter;
		return this;
	}
	visible(visible) {
		this._field.visible = visible;
		return this;
	}
	defaultValue(defaultValue) {
		this._field.defaultValue = defaultValue;
		return this;
	}
	onChange(onChange) {
		this._field.onChange = onChange;
		return this;
	}
	onSelect(onSelect) {
		this._field.onSelect = onSelect;
		return this;
	}
	onClick(onClick) {
		this._field.onClick = onClick;
		return this;
	}
	autoSelectIfSingle(value) {
		this._field.autoSelectIfSingle = value;
		return this;
	}
	readonly(readonly) {
		this._field.readonly = readonly;
		return this;
	}
	disabled(disabled) {
		this._field.disabled = disabled;
		return this;
	}
	hidden(hidden) {
		this._field.hidden = hidden;
		return this;
	}
	required(required) {
		this._field.required = required;
		return this;
	}
	precision(value) {
		this._field.precision = value;
		return this;
	}
	accept(value) {
		this._field.accept = value;
		return this;
	}
	buttons(value) {
		this._field.buttons = value;
		return this;
	}
	searchEnabled(value) {
		this._field.searchEnabled = value;
		return this;
	}
	searchExpr(value) {
		this._field.searchExpr = value;
		return this;
	}
	helpText(value) {
		this._field.helpText = value;
		return this;
	}
	contentRender(value) {
		this._field.contentRender = value;
		return this;
	}
	visibleOnForm(value) {
		this._field.visibleOnForm = value;
		return this;
	}
	multiple(value) {
		this._field.multiple = value;
		return this;
	}
	sendAsString(value) {
		this._field.sendAsString = value;
		return this;
	}
	maxLength(value) {
		this._field.maxLength = value;
		return this;
	}
	visibleWhen(fn) {
		this._field.visibleWhen = fn;
		return this;
	}
	disabledWhen(fn) {
		this._field.disabledWhen = fn;
		return this;
	}
	computed(fn) {
		this._field.computed = fn;
		return this;
	}
	defaultWhen(fn) {
		this._field.defaultWhen = fn;
		return this;
	}
	requiredWhen(fn) {
		this._field.requiredWhen = fn;
		return this;
	}
	clearWhenHidden(value = true) {
		this._field.clearWhenHidden = value;
		return this;
	}
	dependsOn(fields) {
		this._field.dependsOn = fields;
		return this;
	}
	permissions(value) {
		this._field.permissions = value;
		return this;
	}
	build() {
		return { ...this._field };
	}
};
//#endregion
//#region assets/crud/adapter/RestAdapter.ts
/**
* Backend adapter for plain OpenAPI / REST backends.
*
* Conventions assumed:
* - Records use a plain `id` field (numeric or string UUID) as their identifier.
* - Entity fields are serialized as scalar IDs in POST/PATCH bodies.
* - Collection responses are either a plain array or `{ items: [...], total: N }`.
*   Also handles `{ data: [...], total: N }` (common in Laravel / many REST frameworks).
*/
const RestAdapter = {
	getRowId(record, idField) {
		const direct = record[idField];
		if (direct !== void 0 && direct !== null) return direct;
		const id = record["id"];
		if (id !== void 0 && id !== null) return id;
		return "";
	},
	buildItemUrl(baseUrl, id) {
		return `${baseUrl.replace(/\/+$/, "")}/${id}`;
	},
	serializeEntityRef(field, rawValue) {
		if (rawValue === null || rawValue === void 0 || rawValue === "" || rawValue === -999) return;
		if (typeof rawValue === "object") {
			const entity = rawValue;
			const idValue = entity[field.valueField] ?? entity["id"];
			if (idValue !== void 0 && idValue !== null) return idValue;
			return;
		}
		return rawValue;
	},
	normalizeEntityValue(rawValue, field) {
		if (typeof rawValue === "object" && rawValue !== null) {
			const entity = rawValue;
			const directValue = entity[field.valueField];
			if (directValue !== void 0 && directValue !== null) return directValue;
			const id = entity["id"];
			if (id !== void 0 && id !== null) return id;
		}
		return rawValue;
	},
	getEntityOptionKey(item, field) {
		return item[field.valueField] ?? item["value"] ?? item["id"];
	},
	parseListResponse(response) {
		if (Array.isArray(response)) return {
			items: response,
			total: response.length
		};
		if (response === null || typeof response !== "object") return {
			items: [],
			total: 0
		};
		const r = response;
		const items = r["items"] ?? r["data"] ?? r["results"];
		if (Array.isArray(items)) return {
			items,
			total: Number(r["total"] ?? r["count"] ?? items.length)
		};
		return {
			items: [],
			total: 0
		};
	},
	synthesizeEntityKey(_field, _entityValue) {}
};
//#endregion
export { AuditTrailPanel, CrudPage, DATA_GRID_EVENTS, NativeDataGridView as DataGridView, CrudDialogView as DialogView, FORM_EVENTS, FieldBuilder, FieldType, NativeFormView as FormView, HydraAdapter, ResourceSchemaProvider, ResourceStoreProvider, RestAdapter, SmartCrudPage, SmartCrudRolesProvider, checkboxField, computeSummaryValue, createCrudEvents, currencyField, dateField, datetimeField, defineFieldContract, defineFields, defineResource, entityField, enumField, formatSummaryValue, identityField, imageField, noneField, numberField, passwordField, resolveSummaryText, selectField, switchField, textField, textareaField, useResourceStoreFactory, useSmartCrudRoles, validateFieldContract };
