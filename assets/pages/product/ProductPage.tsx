import {useEffect} from "react";
import Product from "../Product";

export const ProductPage = () => {
    const container = "wrapper"
    const product = new Product(container)
    let alreadyRender = false;

    useEffect(() => {
        if (!alreadyRender) {
            product.render()
            alreadyRender = true
        }
    }, [])

    return (
        <div id={container}>
        </div>
    );
};