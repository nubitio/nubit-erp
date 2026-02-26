export const createCrudEvents = <TPrefix extends string>(prefix: TPrefix) =>
  ({
    ADD: `${prefix}:add`,
    EDIT: `${prefix}:edit`,
    DELETE: `${prefix}:delete`,
    SAVE: `${prefix}:save`,
    CANCEL: `${prefix}:cancel`,
    SUCCESS: `${prefix}:success`,
    LOADING: `${prefix}:loading`,
  }) as const;
