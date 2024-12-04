import React from 'react';
import {Navigate, Route, Routes} from 'react-router-dom';
import POSPage from "./pages/POS/POSPage";

export const FullScreenContent = () => {
    return (
        <Routes>
            <Route
                path='/pos'
                element={
                    <POSPage/>
                }
            />
            <Route path='*' element={<Navigate to='/pos'/>}/>
        </Routes>
    );
};
