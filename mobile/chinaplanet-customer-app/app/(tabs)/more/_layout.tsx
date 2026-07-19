import { Stack } from 'expo-router';

import { palette } from '@/src/theme';

const childHeader = {
  headerStyle: { backgroundColor: palette.navy },
  headerTintColor: palette.white,
  headerBackTitle: 'رجوع',
};

export default function MoreLayout() {
  return (
    <Stack screenOptions={{ contentStyle: { backgroundColor: palette.background } }}>
      <Stack.Screen name="index" options={{ headerShown: false }} />
      <Stack.Screen name="orders" options={{ ...childHeader, title: 'الطلبات / Orders' }} />
      <Stack.Screen name="documents" options={{ ...childHeader, title: 'المستندات / Documents' }} />
      <Stack.Screen name="service-invoices" options={{ ...childHeader, title: 'فواتير الخدمات / Invoices' }} />
      <Stack.Screen name="commercial-documents" options={{ ...childHeader, title: 'المستندات التجارية / Commercial Documents' }} />
      <Stack.Screen name="commercial-document/[id]" options={{ ...childHeader, title: 'المستند التجاري / Commercial Document' }} />
      <Stack.Screen name="reports" options={{ ...childHeader, title: 'تقارير الجودة والخدمة / Reports' }} />
      <Stack.Screen name="report/[id]" options={{ ...childHeader, title: 'التقرير / Report' }} />
    </Stack>
  );
}
