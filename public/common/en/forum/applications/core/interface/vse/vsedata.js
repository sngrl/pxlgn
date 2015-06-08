var ipsVSEData = {
	"sections": {
		"body": {
			"body": {
				"title": "Body",
				"selector": "body",
				"background": {
					"color": "e6e9ed"
				},
				"font": {
					"color": "363636"
				},
				"settings": {
					"background": "page_background",
					"color": "text_color"
				}
			},
			"lightText": {
				"title": "Light text",
				"selector": ".ipsType_light",
				"font": {
					"color": "adadad"
				},
				"settings": {
					"font": "text_light"
				}
			},
			"link": {
				"title": "Link color",
				"selector": "a",
				"font": {
					"color": "255b79"
				},
				"settings": {
					"font": "link_color"
				}
			},
			"linkHover": {
				"title": "Link hover color",
				"selector": "a:hover",
				"font": {
					"color": "cd3816"
				},
				"settings": {
					"font": "link_hover_color"
				}
			}
		},
		"header": {
			"appBar": {
				"title": "App/search bar",
				"selector": "#elSearchNavContainer, #elMobileNav",
				"background": {
					"color": "283a4a"
				},
				"settings": {
					"background": "main_nav"
				}
			},
			"headerBar": {
				"title": "Header",
				"selector": "#ipsLayout_header > header",
				"background": {
					"color": "3d5e78"
				},
				"settings": {
					"background": "header"
				}
			},
			"siteName": {
				"title": "Site name text",
				"selector": "#elSiteTitle",
				"font": {
					"color": "ffffff"
				}
			}
		},
		"buttons": {
			"normalButton": {
				"title": "Normal button",
				"selector": ".ipsApp .ipsButton_normal",
				"background": {
					"color": "417ba3"
				},
				"font": {
					"color": "ffffff"
				},
				"settings": {
					"background": "normal_button",
					"font": "normal_button_font"
				}
			},
			"primaryButton": {
				"title": "Primary button",
				"selector": ".ipsApp .ipsButton_primary",
				"background": {
					"color": "262e33"
				},
				"font": {
					"color": "ffffff"
				},
				"settings": {
					"background": "primary_button",
					"font": "primary_button_font"
				}
			},
			"importantButton": {
				"title": "Important button",
				"selector": ".ipsApp .ipsButton_important",
				"background": {
					"color": "94a66a"
				},
				"font": {
					"color": "ffffff"
				},
				"settings": {
					"background": "important_button",
					"font": "important_button_font"
				}
			},
			"alternateButton": {
				"title": "Alternate button",
				"selector": ".ipsApp .ipsButton_alternate",
				"background": {
					"color": "2d4760"
				},
				"font": {
					"color": "ffffff"
				},
				"settings": {
					"background": "alternate_button",
					"font": "alternate_button_font"
				}
			},
			"lightButton": {
				"title": "Light button",
				"selector": ".ipsApp .ipsButton_light",
				"background": {
					"color": "f0f0f0"
				},
				"font": {
					"color": "333333"
				},
				"settings": {
					"background": "light_button",
					"font": "light_button_font"
				}
			},
			"veryLightButton": {
				"title": "Very light button",
				"selector": ".ipsApp .ipsButton_veryLight",
				"background": {
					"color": "ffffff"
				},
				"font": {
					"color": "333333"
				},
				"settings": {
					"background": "very_light_button",
					"font": "very_light_button_font"
				}
			},
			"buttonBar": {
				"title": "Button Bar",
				"selector": ".ipsButtonBar",
				"background": {
					"color": "3d5e78"
				},
				"settings": {
					"background": "button_bar"
				}
			}
		},
		"backgrounds": {
			"areaBackground": {
				"title": "Area background",
				"selector": ".ipsAreaBackground",
				"background": {
					"color": "ebebeb"
				},
				"settings": {
					"background": "area_background"
				}
			},
			"areaBackgroundLight": {
				"title": "Light area background",
				"selector": ".ipsAreaBackground_light",
				"background": {
					"color": "fafafa"
				},
				"settings": {
					"background": "area_background_light"
				}
			},
			"areaBackgroundReset": {
				"title": "Reset area background",
				"selector": ".ipsAreaBackground_reset",
				"background": {
					"color": "ffffff"
				},
				"settings": {
					"background": "area_background_reset"
				}
			},
			"areaBackgroundDark": {
				"title": "Dark area background",
				"selector": ".ipsAreaBackground_dark",
				"background": {
					"color": "262e33"
				},
				"settings": {
					"background": "area_background_dark"
				}
			}
		},
		"other": {
			"sectionTitle": {
				"title": "Section title bar",
				"selector": ".ipsType_sectionTitle",
				"background": {
					"color": "283a4a"
				},
				"font": {
					"color": "ffffff"
				},
				"settings": {
					"background": "section_title",
					"font": "section_title_font"
				}
			},
			"profileHeader": {
				"title": "Default profile header",
				"selector": "#elProfileHeader",
				"background": {
					"color": "262e33"
				},
				"settings": {
					"background": "profile_header"
				}
			},
			"widgetTitleBar": {
				"title": "Widget Title Bar",
				"selector": ".ipsWidget.ipsWidget_vertical .ipsWidget_title",
				"background": {
					"color": "283a4a"
				},
				"settings": {
					"background": "widget_title_bar"
				}
			}
		}
	}
};

var colorizer = {
	primaryColor: {
		"body": [ 'background' ],
		"link": [ 'font' ],
		"appBar": [ 'background' ],
		"headerBar": [ 'background' ],
		"normalButton": [ 'background' ],
		"primaryButton": [ 'background' ],
		"alternateButton": [ 'background' ],
		"sectionTitle": [ 'background' ],
		"areaBackgroundDark": [ 'background' ],
		"profileHeader": [ 'background' ],
		"link": [ 'font' ],
		"widgetTitleBar": [ 'background' ],
		"buttonBar": [ 'background' ]
	},
	secondaryColor: {
		"linkHover": [ 'font' ],
		"importantButton": [ 'background' ]
	},
	tertiaryColor: {
		"areaBackground": [ 'background' ],
		"areaBackgroundLight": [ 'background' ],
		"areaBackgroundReset": [ 'background' ]
	},
	textColor: {
		"body": [ 'font' ],
		"lightText": [ 'font' ]
	},
	startColors: {
		"primaryColor": "3a5a78",
		"secondaryColor": "cd3816",
		"tertiaryColor": "f3f3f3",
		"textColor": "404040"
	}
};