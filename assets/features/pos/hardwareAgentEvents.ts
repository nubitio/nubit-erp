const HARDWARE_CONFIG_CHANGED_EVENT = 'efact:hardware-config-changed';

export const notifyHardwareConfigChanged = () => {
  window.dispatchEvent(new Event(HARDWARE_CONFIG_CHANGED_EVENT));
};

export const subscribeToHardwareConfigChanges = (listener: () => void) => {
  window.addEventListener(HARDWARE_CONFIG_CHANGED_EVENT, listener);
  return () => window.removeEventListener(HARDWARE_CONFIG_CHANGED_EVENT, listener);
};
