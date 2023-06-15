import {useEffect} from "react";
import Company from "../Company";
import "./CompanyPage.scss";

export const CompanyPage = () => {
    const container = "wrapper"
    const company = new Company(container)
    let alreadyRender = false;

    useEffect(() => {
        if (!alreadyRender) {
            company.render()
            alreadyRender = true
        }
    }, [])

    return (
        <div id={container}>
        </div>
    );
};
