import { useCallback, useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';

import { authService } from '@features/auth';
import { branchService } from '@features/branch';
import { resolveBranchSelectionRedirect, resolvePostLoginRedirect } from '@shared/lib/authRedirect';
import { requestAuthSessionSync } from '@shared/lib/authSessionSync';
import toast from '@shared/lib/SharedUtils';
import { useScreenSize } from '@nubitio/admin';
import { Button } from '@nubitio/ui';

import { AppDialog } from './AppDialog';

import './BranchPicker.scss';

interface Branch {
  id: string;
  name: string;
}

interface BranchPickerProps {
  visible: boolean;
  setVisible: (value: boolean) => void;
  initialBranches?: Branch[];
  redirectPath?: string;
}

export const BranchPicker = ({
  visible,
  setVisible,
  initialBranches = [],
  redirectPath,
}: BranchPickerProps) => {
  const firstOptionRef = useRef<HTMLButtonElement | null>(null);
  const navigate = useNavigate();
  const { isXSmall } = useScreenSize();
  const [branches, setBranches] = useState<Branch[]>([]);
  const [selectedBranch, setSelectedBranch] = useState<Branch | null>(null);
  const [selecting, setSelecting] = useState(false);
  const [loadingBranches, setLoadingBranches] = useState(false);
  const [hasLoaded, setHasLoaded] = useState(false);

  const close = useCallback(() => {
    setVisible(false);
  }, [setVisible]);

  const onCancelClick = useCallback(() => {
    close();
  }, [close]);

  const onAcceptClick = useCallback(() => {
    if (selectedBranch == null || selecting) {
      toast('Debe seleccionar un local', 'error');
      return;
    }

    setSelecting(true);
    authService.selectBranch(selectedBranch.id).then(() => {
        branchService.setSelectedBranchId(selectedBranch.id);
        branchService.setSelectedBranchName(selectedBranch.name);
        close();
        const nextPath = redirectPath
          ? resolvePostLoginRedirect(redirectPath)
          : resolveBranchSelectionRedirect(undefined, window.location.search, window.location.hash);

        requestAuthSessionSync();
        navigate(nextPath, { replace: true });
      }).catch(() => {
        setSelecting(false);
      }).finally(() => {
        setSelecting(false);
      });
  }, [selectedBranch, selecting, close, navigate, redirectPath]);

  const loadBranchData = useCallback((data: Branch[]) => {
    setBranches(data);

    const lastBranchId = branchService.getSelectedBranchId();
    const rememberedBranch = data.find((item) => item.id === lastBranchId) ?? null;

    if (rememberedBranch) {
      setSelectedBranch(rememberedBranch);
    }

    setHasLoaded(true);
    setLoadingBranches(false);
  }, []);

  useEffect(() => {
    if (visible && branches.length > 0) {
      firstOptionRef.current?.focus();
    }
  }, [visible, branches.length]);

  useEffect(() => {
    if (!visible || initialBranches.length === 0) {
      return;
    }

    // eslint-disable-next-line react-hooks/set-state-in-effect -- loading initial branch options into local popup state is intentional during visibility sync
    loadBranchData(initialBranches);
  // eslint-disable-next-line react-hooks/exhaustive-deps -- intentionally run only on visibility toggle and length changes, not on identity changes of the array
  }, [visible, initialBranches.length, loadBranchData]);

  useEffect(() => {
    // Skip fetch if initialBranches were provided (or already loaded by that effect)
    if (!visible || hasLoaded || initialBranches.length > 0) {
      return;
    }

    // eslint-disable-next-line react-hooks/set-state-in-effect -- marks the async branch loading state when the picker opens
    setLoadingBranches(true);

    let cancelled = false;
    branchService.getAll().then((response) => {
        if (cancelled) return;
        const data: Branch[] = response?.data ?? [];
        loadBranchData(data);
      }).catch(() => {
        if (cancelled) return;
        setHasLoaded(false);
        setLoadingBranches(false);
      });

    return () => {
      cancelled = true;
    };
  // eslint-disable-next-line react-hooks/exhaustive-deps -- initialBranches.length is checked as a guard but this effect should not re-run when it changes; effect above handles the initialBranches case
  }, [visible, hasLoaded, loadBranchData]);

  useEffect(() => {
    if (!visible) {
      // eslint-disable-next-line react-hooks/set-state-in-effect -- reset local popup state when the shell picker closes
      setSelectedBranch(null);
      // eslint-disable-next-line react-hooks/set-state-in-effect -- reset cached visibility state when the shell picker closes
      setHasLoaded(false);
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps -- intentional: only react to visibility changes, not state setters
  }, [visible]);

  return (
    <AppDialog
      title="Seleccionar local"
      width={480}
      height={360}
      fullScreen={isXSmall}
      visible={visible}
      showCloseButton={false}
      closeOnOutsideClick={!selecting}
      closeOnEscape={!selecting}
      onClose={close}
      className="picker-popup"
      bodyClassName="picker-popup__body"
      footer={
        <>
          <Button variant="secondary" onClick={onCancelClick} disabled={selecting}>
            Cancelar
          </Button>
          <Button variant="primary" onClick={onAcceptClick} disabled={selecting}>
            Aceptar
          </Button>
        </>
      }
    >
      <div className="branch-picker">
        <div className="branch-picker__list" role="radiogroup" aria-label="Locales disponibles">
          {loadingBranches ? (
            <div className="branch-picker__empty">Cargando locales...</div>
          ) : branches.length === 0 ? (
            <div className="branch-picker__empty">No hay locales disponibles</div>
          ) : (
            branches.map((branch, index) => {
              const selected = selectedBranch?.id === branch.id;

              return (
                <button
                  className={`branch-picker__option${selected ? ' is-selected' : ''}`}
                  type="button"
                  role="radio"
                  aria-checked={selected}
                  data-testid="branch-picker-option"
                  data-branch-id={branch.id}
                  key={branch.id}
                  ref={index === 0 ? firstOptionRef : undefined}
                  onClick={() => setSelectedBranch(branch)}
                >
                  <span className="branch-picker__radio" aria-hidden="true" />
                  <span>{branch.name}</span>
                </button>
              );
            })
          )}
        </div>
      </div>
    </AppDialog>
  );
};
