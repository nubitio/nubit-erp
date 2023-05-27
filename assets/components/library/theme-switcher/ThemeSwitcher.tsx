import React, {useCallback, useContext} from 'react';
import Button from 'devextreme-react/button';
import {ThemeContext} from '../../../theme/theme';

export const ThemeSwitcher = () => {
    const themeContext = useContext(ThemeContext);

    const onButtonClick = useCallback(() => {
        themeContext?.switchTheme();
    }, []);

    return <div>
        <Button className='theme-button'
                icon={`${themeContext?.theme !== 'dark' ? 'fa fa-moon' : 'fa fa-sun'}`}
                onClick={onButtonClick}/>
    </div>;
};
