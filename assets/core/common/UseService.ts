import {useEffect, useRef, useState} from "react";
import {Observable} from "rxjs";
import ApiResponse from "../http/ApiResponse";

export function useService<T>(subscription: Observable<ApiResponse<any>>, initialState?: any): [T, boolean, boolean, string] {
    const [data, setData] = useState<T>(initialState);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(false);
    const [errorMessage, setErrorMessage] = useState<string>('');
    const subscriptionRef = useRef(subscription);

    useEffect(() => {
        subscriptionRef.current = subscription;
    }, [subscription]);

    useEffect(() => {
        setLoading(true)
        const subscription$ = subscriptionRef.current.subscribe({
            next: (response: ApiResponse<any>) => {
                setData(response.data);
            },
            error: (error: any) => {
                setError(true);
                setErrorMessage(error.message);
            }
        });
        subscription$.add(() => {
            setLoading(false);
        });
        return () => {
            subscription$.unsubscribe();
        }
    }, []);

    return [data, loading, error, errorMessage];
}