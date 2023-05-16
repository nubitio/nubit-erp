import {SharedUtils} from "./SharedUtils";
import {EventType} from "../event/EventType";
import {bus} from "../event/EventBus";
import FilterRule from "../common/FilterRule";

class ThemeManager {
    private accentColor: string;

    static readonly THEME = {
        LIGHT: 'light',
        DARK: 'dark'
    };

    static readonly DARK_MODE = 'dark-mode';

    constructor() {
        this.accentColor = "";
        this.init();
    }

    init() {
        this.getAccentColor();

        const theme = localStorage.getItem('theme');

        if (theme === ThemeManager.THEME.DARK) {
            document.body.classList.add(ThemeManager.DARK_MODE);
        }

        bus.emit(EventType.THEME_CHANGE, theme);
    }

    toggle() {
        let actualTheme = localStorage.getItem('theme');
        let newTheme = (actualTheme === ThemeManager.THEME.DARK) ? ThemeManager.THEME.LIGHT : ThemeManager.THEME.DARK;

        if (actualTheme === ThemeManager.THEME.DARK) {
            localStorage.setItem('theme', newTheme);
            document.body.classList.remove(ThemeManager.DARK_MODE);
        } else {
            localStorage.setItem('theme', newTheme);
            document.body.classList.add(ThemeManager.DARK_MODE);
        }

        bus.emit(EventType.THEME_CHANGE, newTheme);
    }

    getAccentColor() {
        let accentColor = localStorage.getItem('accentColor');
        if (accentColor) {
            this.accentColor = accentColor;
            this.setupAppColors();
        }

        let filter: FilterRule = {
            field: 'name',
            op: 'equal',
            value: 'app_theme_accent_color'
        }
        fetch('api/settings?filterRules=[' + JSON.stringify(filter) + ']')
            .then(response => response.json())
            .then(result => {
                this.accentColor = result[0].value;
                localStorage.setItem('accentColor', this.accentColor);
                this.setupAppColors();
            })
            .catch(error => SharedUtils.showMessage('Error', error))
    }

    setupAppColors() {
        if (this.accentColor !== null) {
            let contrastColor = this.getContrastColor(this.accentColor);
            document.body.style.setProperty('--accent-color', this.accentColor);
            document.body.style.setProperty('--accent-color-contrast', contrastColor);
        }
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

export const themeManager = new ThemeManager();