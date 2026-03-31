import { useEffect, useRef, useState } from 'react';
import { Observable } from 'rxjs';
import ApiResponse from '../../shared/api/ApiResponse';

export function useService<T>(
  subscription: Observable<ApiResponse<T>>,
  initialState?: T,
): [T, boolean, boolean, string] {
  const [data, setData] = useState<T>(initialState as T);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string>('');
  const subscriptionRef = useRef(subscription);

  useEffect(() => {
    subscriptionRef.current = subscription;
  }, [subscription]);

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- setting loading flag before subscription is standard async pattern
    setLoading(true);
    const subscription$ = subscriptionRef.current.subscribe({
      next: (response: ApiResponse<T>) => {
        setData(response.data);
      },
      error: (err: unknown) => {
        setError(true);
        setErrorMessage(err instanceof Error ? err.message : String(err));
      },
    });
    subscription$.add(() => {
      setLoading(false);
    });
    return () => {
      subscription$.unsubscribe();
    };
  }, []);

  return [data, loading, error, errorMessage];
}
