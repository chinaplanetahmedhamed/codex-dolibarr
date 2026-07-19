import { Ionicons } from '@expo/vector-icons';
import { Tabs } from 'expo-router';

import { HapticTab } from '@/components/haptic-tab';
import { useI18n } from '@/src/i18n/I18nProvider';
import { palette } from '@/src/theme';

export default function TabLayout() {
  const { language } = useI18n();
  return (
    <Tabs
      screenOptions={{
        tabBarActiveTintColor: palette.brandRed,
        tabBarInactiveTintColor: '#728995',
        tabBarStyle: {
          height: 72,
          paddingTop: 8,
          paddingBottom: 9,
          backgroundColor: palette.surface,
          borderTopColor: palette.border,
        },
        tabBarLabelStyle: { fontSize: 10, fontWeight: '800' },
        headerShown: false,
        tabBarButton: HapticTab,
      }}>
      <Tabs.Screen
        name="home"
        options={{
          title: language === 'ar' ? 'الرئيسية' : 'Home',
          tabBarAccessibilityLabel: 'الرئيسية / Home',
          tabBarIcon: ({ color, focused }) => <Ionicons size={focused ? 25 : 23} name={focused ? 'home' : 'home-outline'} color={color} />,
        }}
      />
      <Tabs.Screen
        name="projects"
        options={{
          title: language === 'ar' ? 'المشاريع' : 'Projects',
          tabBarAccessibilityLabel: 'المشاريع / Projects',
          tabBarIcon: ({ color, focused }) => <Ionicons size={focused ? 25 : 23} name={focused ? 'folder-open' : 'folder-open-outline'} color={color} />,
        }}
      />
      <Tabs.Screen
        name="shipments"
        options={{
          title: language === 'ar' ? 'الشحنات' : 'Shipments',
          tabBarAccessibilityLabel: 'الشحنات / Shipments',
          tabBarIcon: ({ color, focused }) => <Ionicons size={focused ? 26 : 24} name={focused ? 'boat' : 'boat-outline'} color={color} />,
        }}
      />
      <Tabs.Screen
        name="account"
        options={{
          title: language === 'ar' ? 'الحساب' : 'Account',
          tabBarAccessibilityLabel: 'الحساب / Account',
          tabBarIcon: ({ color, focused }) => <Ionicons size={focused ? 25 : 23} name={focused ? 'wallet' : 'wallet-outline'} color={color} />,
        }}
      />
      <Tabs.Screen
        name="more"
        options={{
          title: language === 'ar' ? 'المزيد' : 'More',
          tabBarAccessibilityLabel: 'المزيد / More',
          tabBarIcon: ({ color, focused }) => <Ionicons size={focused ? 26 : 24} name={focused ? 'grid' : 'grid-outline'} color={color} />,
        }}
      />
    </Tabs>
  );
}
