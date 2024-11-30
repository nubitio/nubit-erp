import React from 'react';
import {Navigate, Route, Routes} from 'react-router-dom';
import {LoginPage} from "./pages/Login/LoginPage";

export const UnauthenticatedContent = () => {
    return (
        <Routes>
            <Route
                path='/login'
                element={
                    <LoginPage/>
                }
            />
            <Route path='*' element={<Navigate to='/login'/>}/>
        </Routes>
    );
};
