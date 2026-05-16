import { useEffect, useRef, useState } from 'react';
import ApiResponse from '../../shared/api/ApiResponse';

export function useService<T>(
  request: Promise<ApiResponse<T>>,
  initialState?: T,
): [T, boolean, boolean, string] {
  const [data, setData] = useState<T>(initialState as T);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string>('');
  const requestRef = useRef(request);

  useEffect(() => {
    requestRef.current = request;
  }, [request]);

  useEffect(() => {
    let cancelled = false;
    // eslint-disable-next-line react-hooks/set-state-in-effect -- setting loading flag before async call is standard async pattern
    setLoading(true);

    requestRef.current
      .then((response: ApiResponse<T>) => {
        if (!cancelled) setData(response.data);
      })
      .catch((err: unknown) => {
        if (!cancelled) {
          setError(true);
          setErrorMessage(err instanceof Error ? err.message : String(err));
        }
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, []);

  return [data, loading, error, errorMessage];
}
