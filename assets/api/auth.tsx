import {defaultUser} from '../mock/default-user';
import {lastValueFrom} from "rxjs";
import {httpClient} from "../core/http/HttpClient";
import {User} from "../types";
import {Constants} from "../core/common/Constants";

/* eslint-disable @typescript-eslint/no-unused-vars */
export async function signIn(email: string, password: string) {
    try {
        // Send request
        return {
            isOk: true,
            data: {...defaultUser, email},
        };
    } catch {
        return {
            isOk: false,
            message: 'Authentication failed',
        };
    }
}

export async function getUser() {
    let session = JSON.parse(localStorage.getItem('session') ?? '{}')
    let userId = session.user_id
    return lastValueFrom(httpClient.get<User>(`${Constants.API_BASE_URL}users/${userId}`))
}

export async function createAccount(email: string, password: string) {
    try {
        // Send request
        return {
            isOk: true,
        };
    } catch {
        return {
            isOk: false,
            message: 'Failed to create account',
        };
    }
}

export async function changePassword(email: string, recoveryCode?: string) {
    try {
        // Send request
        return {
            isOk: true,
            data: {email},
        };
    } catch {
        return {
            isOk: false,
            message: 'Failed to change password',
        };
    }
}

export async function resetPassword(email: string) {
    try {
        // Send request
        return {
            isOk: true,
        };
    } catch {
        return {
            isOk: false,
            message: 'Failed to reset password',
        };
    }
}
