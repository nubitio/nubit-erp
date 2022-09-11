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

    static imageFormatter(image, name, path = '') {
        if (image) {
            return `<a href="${path}${image}" data-lightbox="${name}">
                    <img src="${path}${image}" style="width: 30px; height: 30px;"  alt=""/>
                </a>`;
        } else {
            return `<a href="https://ui-avatars.com/api/?name=${name}" data-lightbox="${name}">
                    <img src="https://ui-avatars.com/api/?name=${name}" style="width: 30px; height: 30px;"  alt=""/>
                </a>`;
        }
    }
}