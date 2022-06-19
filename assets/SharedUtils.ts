export class SharedUtils {
    static showMessage(title: String, message: string) {
        ($ as any).messager.show({
            title: title,
            msg: message
        });
    }

    static activeFormatter(val) {
        if (Number(val) === 1) {
            return '<span class="badge rounded-pill bg-success">SI</span>';
        } else {
            return '<span class="badge rounded-pill bg-danger">NO</span>';
        }
    }
}