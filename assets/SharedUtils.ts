export class SharedUtils {
    static showMessage(title: String, message: string) {
        ($ as any).messager.show({
            title: title,
            msg: message
        });
    }
}