import React from 'react';
import { Routes, Route, Navigate } from 'react-router-dom';
import { appRoutes } from './app-routes';
import { SideNavOuterToolbar as SideNavBarLayout } from './layouts';
import {userInformation} from "./core/common/UserInformation";

export const Content = () => {
    return (
        <SideNavBarLayout title={userInformation().currentBranchName}>
            <Routes>
                {appRoutes.map(({ path, element }) => (
                    <Route key={path} path={path} element={element} />
                ))}
                <Route path='*' element={<Navigate to='/dashboard' />} />
            </Routes>
        </SideNavBarLayout>
    );
};
