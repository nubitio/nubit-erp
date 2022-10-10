class Messenger {
    show(title: string, message: string) {
        ($ as any).messager.show({
            title: title,
            msg: message
        });
    }

    alert(title: string, message: string) {
        ($ as any).messager.alert(title, message);
    }

    confirm(title: string, message: string, callback: Function) {
        ($ as any).messager.confirm(title, message, callback);
    }

    prompt(title: string, message: string, callback: Function) {
        ($ as any).messager.prompt(title, message, callback);
    }

    progress(title: string, message: string) {
        ($ as any).messager.progress({
            title: title,
            msg: message
        });
    }
}

export const messenger = new Messenger();