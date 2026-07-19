import { Ionicons } from '@expo/vector-icons';
import { useFonts } from 'expo-font';
import { DefaultTheme, ThemeProvider } from '@react-navigation/native';
import { Stack } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { ActivityIndicator, View } from 'react-native';
import 'react-native-reanimated';

import { AuthProvider, useAuth } from '@/src/auth/AuthProvider';
import { I18nProvider } from '@/src/i18n/I18nProvider';
import { palette } from '@/src/theme';

export default function RootLayout() {
  return (
    <I18nProvider>
      <AuthProvider><AppNavigator /></AuthProvider>
    </I18nProvider>
  );
}

function AppNavigator() {
  const [fontsLoaded, fontError] = useFonts(Ionicons.font);
  const { status } = useAuth();
  const navigationTheme = {
    ...DefaultTheme,
    colors: {
      ...DefaultTheme.colors,
      primary: palette.brandRed,
      background: palette.background,
      card: palette.navy,
      text: palette.text,
      border: palette.border,
    },
  };

  if ((!fontsLoaded && !fontError) || status === 'booting') {
    return <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: palette.navy }}><ActivityIndicator color={palette.brandRed} /></View>;
  }

  return (
    <ThemeProvider value={navigationTheme}>
      <Stack screenOptions={{ contentStyle: { backgroundColor: palette.background } }}>
        <Stack.Protected guard={status === 'anonymous'}>
          <Stack.Screen name="index" options={{ headerShown: false }} />
        </Stack.Protected>
        <Stack.Protected guard={status === 'authenticated'}>
          <Stack.Screen name="(tabs)" options={{ headerShown: false }} />
        <Stack.Screen
          name="shipment/[id]"
          options={{
            title: 'تتبع الشحنة / Shipment',
            headerStyle: { backgroundColor: palette.navy },
            headerTintColor: palette.white,
            headerBackTitle: 'رجوع',
          }}
        />
        <Stack.Screen
          name="project/[id]"
          options={{
            title: 'المشروع / Project',
            headerStyle: { backgroundColor: palette.navy },
            headerTintColor: palette.white,
            headerBackTitle: 'رجوع',
          }}
        />
        <Stack.Screen
          name="documents"
          options={{
            title: 'المستندات / Documents',
            headerStyle: { backgroundColor: palette.navy },
            headerTintColor: palette.white,
            headerBackTitle: 'رجوع',
          }}
        />
          <Stack.Screen name="orders" options={{ title: 'الطلبات / Orders', headerStyle: { backgroundColor: palette.navy }, headerTintColor: palette.white }} />
          <Stack.Screen name="transactions" options={{ title: 'كشف الحساب / Transactions', headerStyle: { backgroundColor: palette.navy }, headerTintColor: palette.white }} />
          <Stack.Screen name="service-invoices" options={{ title: 'فواتير الخدمات / Invoices', headerStyle: { backgroundColor: palette.navy }, headerTintColor: palette.white }} />
          <Stack.Screen name="commercial-documents" options={{ title: 'المستندات التجارية / Commercial Documents', headerStyle: { backgroundColor: palette.navy }, headerTintColor: palette.white }} />
          <Stack.Screen name="reports" options={{ title: 'تقارير الجودة والخدمة / Reports', headerStyle: { backgroundColor: palette.navy }, headerTintColor: palette.white }} />
          <Stack.Screen name="commercial-document/[id]" options={{ title: 'المستند التجاري / Commercial Document', headerStyle: { backgroundColor: palette.navy }, headerTintColor: palette.white }} />
          <Stack.Screen name="report/[id]" options={{ title: 'التقرير / Report', headerStyle: { backgroundColor: palette.navy }, headerTintColor: palette.white }} />
        </Stack.Protected>
      </Stack>
      <StatusBar style="light" />
    </ThemeProvider>
  );
}
