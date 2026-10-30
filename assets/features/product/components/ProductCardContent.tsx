import type { CardViewContext } from '@nubitio/crud';
import { productCardModel } from '../model/productCardModel';

/** Presentation only: the base grid owns opening, selection and authorized actions. */
export function ProductCardContent({ row }: CardViewContext) {
  const product = productCardModel(row);
  return (
    <span className="product-catalog-card">
      <span className="product-catalog-card__category">{product.category}</span>
      <strong className="product-catalog-card__name">{product.name}</strong>
      <span className="product-catalog-card__code">{product.code}</span>
      <strong className="product-catalog-card__price">{product.price}</strong>
      <span className={`product-catalog-card__stock product-catalog-card__stock--${product.tone}`}>
        {product.stockLabel}
      </span>
    </span>
  );
}

export function ProductCardMedia({ row }: CardViewContext) {
  const { image } = productCardModel(row);
  return image ? (
    <img src={image} alt="" loading="lazy" />
  ) : (
    <span className="product-catalog-card__placeholder">
      <img src="/assets/images/design-system/catalog-box.svg" alt="" />
    </span>
  );
}
