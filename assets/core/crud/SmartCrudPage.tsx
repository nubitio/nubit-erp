/**
 * SmartCrudPage — currently an alias of CrudPage.
 *
 * In SLICE-005 this wrapper will add a Hydra metadata layer that
 * auto-populates `title` and `fields` from the API's JSON-LD context.
 * For now it is a transparent re-export so pages can already adopt the
 * new import without needing to change again after SLICE-005 lands.
 */
export { CrudPage as SmartCrudPage } from './CrudPage';
