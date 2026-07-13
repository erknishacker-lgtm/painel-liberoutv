package com.liberou.tv.panel;

import android.app.Activity;
import android.app.AlertDialog;
import android.app.ProgressDialog;
import android.content.Context;
import android.content.DialogInterface;
import android.content.Intent;
import android.content.SharedPreferences;
import android.content.pm.PackageInfo;
import android.net.Uri;
import android.os.Build;
import android.widget.Toast;
import java.io.File;
import java.io.FileOutputStream;
import java.io.InputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import org.json.JSONObject;

/**
 * Checa versão no painel e oferece / força atualização do APK.
 */
public final class AppUpdate {

    private AppUpdate() {}

    /** Chamar no Splash / Login / Dashboard. */
    public static void check(final Activity act) {
        if (act == null) {
            return;
        }
        Thread t = new Thread(new Runnable() {
            @Override
            public void run() {
                try {
                    // Garante config fresca
                    PanelClient.b(act.getApplicationContext());
                    SharedPreferences p = act.getSharedPreferences("liberou_panel", 0);
                    String json = p.getString("config_json", "");
                    if (json == null || json.length() < 10) {
                        return;
                    }
                    JSONObject o = new JSONObject(json);
                    final String latest = o.optString("app_version_latest", "").trim();
                    final String min = o.optString("app_version_min", "").trim();
                    final String apkUrl = o.optString("app_apk_url", "").trim();
                    String message = o.optString("app_update_message", "").trim();
                    boolean forceFlag = o.optBoolean("app_update_force", false)
                            || "1".equals(o.optString("app_update_force", "0"));

                    if (apkUrl.length() < 10 || !apkUrl.startsWith("http")) {
                        return;
                    }
                    if (latest.length() == 0 && min.length() == 0) {
                        return;
                    }

                    String current = currentVersion(act);
                    boolean needByLatest = latest.length() > 0 && cmpVersion(current, latest) < 0;
                    boolean needByMin = min.length() > 0 && cmpVersion(current, min) < 0;
                    if (!needByLatest && !needByMin) {
                        return;
                    }
                    final boolean force = forceFlag || needByMin;
                    if (message.length() == 0) {
                        message = force
                                ? ("Atualização obrigatória para a versão " + (latest.length() > 0 ? latest : min) + ".")
                                : ("Nova versão " + latest + " disponível. Recomendamos atualizar.");
                    }
                    final String msgFinal = message + "\n\nSua versão: " + current
                            + (latest.length() > 0 ? ("\nNova: " + latest) : "");

                    act.runOnUiThread(new Runnable() {
                        @Override
                        public void run() {
                            showDialog(act, msgFinal, apkUrl, force);
                        }
                    });
                } catch (Throwable ignored) {
                }
            }
        }, "liberou-app-update");
        t.start();
    }

    private static void showDialog(final Activity act, String message, final String apkUrl, final boolean force) {
        try {
            if (act.isFinishing()) {
                return;
            }
            AlertDialog.Builder b = new AlertDialog.Builder(act);
            b.setTitle(force ? "Atualização obrigatória" : "Nova versão LIBEROU");
            b.setMessage(message);
            b.setCancelable(!force);
            b.setPositiveButton("Atualizar agora", new DialogInterface.OnClickListener() {
                @Override
                public void onClick(DialogInterface dialog, int which) {
                    startDownload(act, apkUrl, force);
                }
            });
            if (!force) {
                b.setNegativeButton("Depois", null);
            }
            AlertDialog d = b.create();
            d.setCanceledOnTouchOutside(!force);
            d.show();
        } catch (Throwable ignored) {
            try {
                Toast.makeText(act, "Atualização disponível", Toast.LENGTH_LONG).show();
            } catch (Throwable ignored2) {
            }
        }
    }

    private static void startDownload(final Activity act, final String apkUrl, final boolean force) {
        final ProgressDialog pd = new ProgressDialog(act);
        try {
            pd.setTitle("LIBEROU TV");
            pd.setMessage("Baixando atualização…");
            pd.setIndeterminate(true);
            pd.setCancelable(false);
            pd.show();
        } catch (Throwable ignored) {
        }

        Thread t = new Thread(new Runnable() {
            @Override
            public void run() {
                File out = null;
                try {
                    File dir = new File(act.getFilesDir(), "updates");
                    if (!dir.exists()) {
                        dir.mkdirs();
                    }
                    out = new File(dir, "liberou_update.apk");
                    byte[] data = download(apkUrl);
                    if (data == null || data.length < 1000) {
                        throw new RuntimeException("download vazio");
                    }
                    FileOutputStream fos = new FileOutputStream(out);
                    fos.write(data);
                    fos.close();
                    final File apk = out;
                    act.runOnUiThread(new Runnable() {
                        @Override
                        public void run() {
                            try {
                                pd.dismiss();
                            } catch (Throwable ignored) {
                            }
                            installApk(act, apk);
                        }
                    });
                } catch (final Throwable e) {
                    act.runOnUiThread(new Runnable() {
                        @Override
                        public void run() {
                            try {
                                pd.dismiss();
                            } catch (Throwable ignored) {
                            }
                            try {
                                // Fallback: abre URL no navegador
                                Intent i = new Intent(Intent.ACTION_VIEW, Uri.parse(apkUrl));
                                i.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
                                act.startActivity(i);
                            } catch (Throwable ignored2) {
                                Toast.makeText(act, "Falha ao baixar. Tente de novo.", Toast.LENGTH_LONG).show();
                            }
                            if (force) {
                                // reabre o diálogo
                                check(act);
                            }
                        }
                    });
                }
            }
        }, "liberou-apk-dl");
        t.start();
    }

    private static void installApk(Activity act, File apk) {
        try {
            if (Build.VERSION.SDK_INT >= 26) {
                // REQUEST_INSTALL_PACKAGES — se não tiver, tenta mesmo assim / settings
                try {
                    if (!act.getPackageManager().canRequestPackageInstalls()) {
                        Intent s = new Intent(android.provider.Settings.ACTION_MANAGE_UNKNOWN_APP_SOURCES);
                        s.setData(Uri.parse("package:" + act.getPackageName()));
                        s.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
                        act.startActivity(s);
                        Toast.makeText(act, "Permita instalar apps deste app e toque Atualizar de novo.", Toast.LENGTH_LONG).show();
                        return;
                    }
                } catch (Throwable ignored) {
                }
            }
            Uri uri;
            try {
                // androidx.core.content.FileProvider via reflection (já no APK)
                Class<?> fp = Class.forName("androidx.core.content.FileProvider");
                uri = (Uri) fp.getMethod("getUriForFile", Context.class, String.class, File.class)
                        .invoke(null, act, "com.liberou.tv.fileprovider", apk);
            } catch (Throwable t) {
                uri = Uri.fromFile(apk);
            }
            Intent intent = new Intent(Intent.ACTION_VIEW);
            intent.setDataAndType(uri, "application/vnd.android.package-archive");
            intent.addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION);
            intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
            act.startActivity(intent);
        } catch (Throwable e) {
            try {
                // fallback file:// (Android antigo)
                Intent intent = new Intent(Intent.ACTION_VIEW);
                intent.setDataAndType(Uri.fromFile(apk), "application/vnd.android.package-archive");
                intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
                act.startActivity(intent);
            } catch (Throwable e2) {
                Toast.makeText(act, "Não foi possível abrir o instalador.", Toast.LENGTH_LONG).show();
            }
        }
    }

    private static byte[] download(String urlStr) throws Exception {
        HttpURLConnection c = (HttpURLConnection) new URL(urlStr).openConnection();
        c.setConnectTimeout(20000);
        c.setReadTimeout(120000);
        c.setRequestMethod("GET");
        c.setRequestProperty("User-Agent", "LIBEROU-AppUpdate/1.0");
        c.setInstanceFollowRedirects(true);
        int code = c.getResponseCode();
        if (code >= 400) {
            c.disconnect();
            return null;
        }
        InputStream in = c.getInputStream();
        java.io.ByteArrayOutputStream bos = new java.io.ByteArrayOutputStream();
        byte[] buf = new byte[8192];
        int n;
        while ((n = in.read(buf)) >= 0) {
            bos.write(buf, 0, n);
        }
        in.close();
        c.disconnect();
        return bos.toByteArray();
    }

    private static String currentVersion(Context ctx) {
        try {
            PackageInfo pi = ctx.getPackageManager().getPackageInfo(ctx.getPackageName(), 0);
            return pi.versionName != null ? pi.versionName : "0";
        } catch (Throwable e) {
            return "0";
        }
    }

    /** Compara semver simples: -1 se a&lt;b, 0 igual, 1 se a&gt;b */
    static int cmpVersion(String a, String b) {
        if (a == null) {
            a = "0";
        }
        if (b == null) {
            b = "0";
        }
        a = a.trim();
        b = b.trim();
        String[] pa = a.split("[^0-9]+");
        String[] pb = b.split("[^0-9]+");
        int n = Math.max(pa.length, pb.length);
        for (int i = 0; i < n; i++) {
            int va = 0;
            int vb = 0;
            try {
                if (i < pa.length && pa[i].length() > 0) {
                    va = Integer.parseInt(pa[i]);
                }
            } catch (Throwable ignored) {
            }
            try {
                if (i < pb.length && pb[i].length() > 0) {
                    vb = Integer.parseInt(pb[i]);
                }
            } catch (Throwable ignored) {
            }
            if (va < vb) {
                return -1;
            }
            if (va > vb) {
                return 1;
            }
        }
        return 0;
    }
}
