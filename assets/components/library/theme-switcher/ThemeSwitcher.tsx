import React, {useCallback, useContext} from 'react';
import Button from 'devextreme-react/button';
import {ThemeContext} from '../../../theme/theme';
import {themeManager} from "../../../core/utils/ThemeManager";

export const ThemeSwitcher = () => {
    const themeContext = useContext(ThemeContext);

    const onButtonClick = useCallback(() => {
        themeContext?.switchTheme();
        themeManager.toggle();
    }, []);

    return <div>
        <Button className='theme-button'
                icon={`${themeContext?.theme !== 'dark' ? 'fa fa-moon' : 'fa fa-sun'}`}
                onClick={onButtonClick}/>
    </div>;
};
