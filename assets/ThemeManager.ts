import {SharedUtils} from "./utils/SharedUtils";
import {EventType} from "./EventType";
import {bus} from "./EventBus";
import {Action} from "./Action";

export default class ThemeManager {
    private accentColor: string;

    static readonly THEME = {
        LIGHT: 'light',
        DARK: 'dark'
    };

    static readonly CLASS = {
        LIGHT: 'light-theme',
        DARK: 'dark-theme'
    }

    constructor() {
        this.accentColor = "";
        this.init();
    }

    init() {
        this.getAccentColor();

        const theme = localStorage.getItem('theme');

        if (theme === ThemeManager.THEME.DARK) {
            document.body.classList.add(ThemeManager.CLASS.DARK);
        } else {
            document.body.classList.add(ThemeManager.CLASS.LIGHT);
        }

        bus.emit(new Action(EventType.THEME_CHANGE, theme));
    }

    toggle() {
        let actualTheme = localStorage.getItem('theme');
        let newTheme = (actualTheme === ThemeManager.THEME.DARK) ? ThemeManager.THEME.LIGHT : ThemeManager.THEME.DARK;

        if (actualTheme === ThemeManager.THEME.DARK) {
            localStorage.setItem('theme', newTheme);
            document.body.classList.remove(ThemeManager.CLASS.DARK);
            document.body.classList.add(ThemeManager.CLASS.LIGHT);
        } else {
            localStorage.setItem('theme', newTheme);
            document.body.classList.remove(ThemeManager.CLASS.LIGHT);
            document.body.classList.add(ThemeManager.CLASS.DARK);
        }

        bus.emit(new Action(EventType.THEME_CHANGE, newTheme));
    }

    getAccentColor() {
        let accentColor = localStorage.getItem('accentColor');
        if (accentColor !== null) {
            this.accentColor = accentColor;
        }

        fetch('home/theme')
            .then(response => response.json())
            .then(result => {
                this.accentColor = result.accentColor;
                localStorage.setItem('accentColor', this.accentColor);
                document.body.style.setProperty('--accent-color', this.accentColor);
                let contrastColor = this.getContrastColor(this.accentColor);
                document.body.style.setProperty('--accent-color-contrast', contrastColor);
            })
            .catch(error => SharedUtils.showMessage('Error', error))
    }

    getContrastColor(color: string) {
        let rgbColor = color.split(' ');
        let r = parseInt(rgbColor[0]);
        let g = parseInt(rgbColor[1]);
        let b = parseInt(rgbColor[2]);
        let sum = ((r * 299) + (g * 587) + (b * 114)) / 1000;
        return (sum >= 128) ? '#000' : '#fff';
    }
}