export function syncComputedValuesIntoFormData(
  formData: Record<string, unknown>,
  computedFieldNames: string[],
  computedValues: Record<string, unknown>,
): { changed: boolean; nextFormData: Record<string, unknown> } {
  let changed = false;
  const nextFormData = { ...formData };

  computedFieldNames.forEach((name) => {
    const hasComputedValue = Object.prototype.hasOwnProperty.call(computedValues, name);
    const value = hasComputedValue ? computedValues[name] : undefined;

    if (nextFormData[name] !== value) {
      nextFormData[name] = value;
      changed = true;
    }
  });

  return { changed, nextFormData };
}
