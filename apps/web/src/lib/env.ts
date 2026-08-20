export type PublicEnv = {
  appEnv: string;
  appDomain: string;
  supabaseUrl: string;
  supabasePublishableKey: string;
};

export function getPublicEnv(): PublicEnv {
  return {
    appEnv: process.env.NEXT_PUBLIC_APP_ENV ?? "local",
    appDomain: process.env.NEXT_PUBLIC_APP_DOMAIN ?? "http://localhost:3000",
    supabaseUrl: process.env.NEXT_PUBLIC_SUPABASE_URL ?? "",
    supabasePublishableKey: process.env.NEXT_PUBLIC_SUPABASE_PUBLISHABLE_KEY ?? ""
  };
}

export function hasSupabasePublicEnv(env = getPublicEnv()): boolean {
  return Boolean(env.supabaseUrl && env.supabasePublishableKey);
}
