export type Appearance = 'light' | 'dark' | 'system';
export type ResolvedAppearance = 'light' | 'dark';

export type AppVariant = 'header' | 'sidebar';

export type TwoFactorManualSetup = {
    reason: string;
    secretKey: string;
    qrCodeSvg: string;
    recoveryCodes: string[];
};

export type TwoFactorNotification = {
    type: 'sent_to_creator';
    sent_to: string;
};
