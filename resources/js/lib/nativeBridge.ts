type NativeBridgeMessage =
    | {
          event: "AUTH_STATE";
          payload: { authenticated: boolean; userId: number | null };
      }
    | { event: "NAVIGATION"; payload: { url: string } }
    | {
          event: "SHARE";
          payload: { title: string; text?: string; url: string };
      };

declare global {
    interface Window {
        ReactNativeWebView?: { postMessage(message: string): void };
    }
}

const postToNative = (message: NativeBridgeMessage): boolean => {
    if (!window.ReactNativeWebView) return false;

    window.ReactNativeWebView.postMessage(
        JSON.stringify({ protocol: "playnexus.native.v1", ...message }),
    );

    return true;
};

export const notifyNativeAuthState = (userId: number | null): void => {
    postToNative({
        event: "AUTH_STATE",
        payload: { authenticated: userId !== null, userId },
    });
};

export const notifyNativeNavigation = (url: string): void => {
    postToNative({ event: "NAVIGATION", payload: { url } });
};

export const requestNativeShare = (
    title: string,
    url: string,
    text?: string,
): boolean => postToNative({ event: "SHARE", payload: { title, text, url } });
