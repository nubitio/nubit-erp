import {createRoot} from "react-dom/client";
import {Routes} from "./routes";
import {messenger} from "../utils/Messenger";
import React from "react";

class Router {
    private readonly container: string;

    constructor(container: string) {
        this.container = container;
    }

    navigate(path: string) {
        let params: any = {};
        let route = Routes.find((route) => {
            const routePath = route.path.split('/');
            const pathParts = path.split('/');
            if (routePath.length !== pathParts.length) {
                return false;
            }
            for (let i = 0; i < routePath.length; i++) {
                if (routePath[i].startsWith(':')) {
                    params[routePath[i].substring(1)] = pathParts[i];
                    continue;
                }
                if (routePath[i] !== pathParts[i]) {
                    return false;
                }
            }
            return true;
        });

        if (route) {
            this.loadPage(route.component, params);
        } else {
            console.log('Route not found');
        }
    }

    private loadPage(page: string, params?: any) {
        const reactRoot = createRoot(document.getElementById(this.container) as HTMLElement);
        import(`../../pages/${page}.tsx`).then((module) => {
            reactRoot.render(React.createElement(module.default, params));
        }).catch((error) => messenger.alert('Error', error.message));
    }
}

export const router = new Router('wrapper');